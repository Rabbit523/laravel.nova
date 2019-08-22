<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use League\Csv\Reader;
use Cake\Chronos\Chronos;

class DataSource extends Model
{
    use Traits\UuidModelTrait, SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'datasources';

    protected $guarded = ['id', 'user_id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'meta' => 'array',
        'record' => 'array',
    ];

    /**
     * Get the integration that triggered the data source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get record transaction where this datasource was the source
     *
     * @relation('MorphOne')
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'source', 'transaction_sources');
    }

    /**
     * Get the project that owns the data source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that owns the data source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    private function statusError($message, $log = false)
    {
        $this->status = 'error';
        $this->meta = array_merge(array_wrap($this->meta), ['message' => $message]);
        $this->save();
        if ($log) {
            logger()->error($message);
        }
        return false;
    }

    public function parse()
    {
        logger()->debug("parsing datasource", ['type' => $this->type]);
        switch ($this->type) {
            case 'stripe':
                return $this->parseStripe();
            case 'connect':
                return $this->parseConnect();
            case 'csv':
                return $this->parseCSV();
            default:
                logger()->error('unknown datasource type', ['type' => $this->type]);
                return false;
        }
    }

    public function parseCSV()
    {
        try {
            $csv = Reader::createFromPath(Storage::path($this->meta['path']), 'r');
            $csv->setHeaderOffset(0);
            $header = $csv->getHeader();
            if (count($header) < 3) {
                return $this->statusError('Invalid csv file');
            }
            $category_col = $header[0];
            $name_col = $header[1];
            logger()->debug('csv headers:', [
                'category' => $category_col,
                'name' => $name_col,
                'is_date' => check_is_date(date_parse_fixed(str_replace('/', '-', $name_col))),
                'header' => $header,
            ]);

            if (check_is_date(date_parse_fixed(str_replace('/', '-', $name_col)))) {
                throw new \Exception(
                    Lang::getFromJson(
                        "The file you are trying to upload must contain Kinchaku's 'cost_category' as a first column and 'cost_title' as a second column."
                    )
                );
            }

            $lines = $csv->getRecords();
            // project duration will be updated based on the number of monthly records
            $duration = 12;
            $type = array_get($this->record, 'type');
            if (!$type) {
                return $this->statusError('Record type not set');
            }
            $records_count = 0;
            $errors = [];
            // TODO: use DB transaction here?
            /*
            DB::transaction(function () {
            });
            */
            foreach ($lines as $row => $line) {
                // if it's revenue, use first column as plan, second as contact name
                $cat = trim(array_pull($line, $category_col));
                $sub = false;
                logger()->debug('name_col', [$name_col]);
                $name = trim(array_pull($line, $name_col));
                // skip totals row
                if (
                    $name == '合計' ||
                    strtolower($name) == 'total' ||
                    strtolower($name) == 'totals'
                ) {
                    continue;
                }

                $plan = null;
                $product = null;
                if ($type == 'revenue') {
                    $product = Product::GetByName($this->project->user, $cat, true);
                    $category_code = 'revenue';
                    $product->projects()->syncWithoutDetaching([$this->project->id]);
                    $plan = $product->plans()->firstOrCreate(
                        [
                            'name' => $name,
                        ],
                        [
                            'name' => $name,
                            'interval' => $product->type == 'retail' ? 'once' : 'month',
                            'amount' => 0,
                            'currency' => $this->project->currency,
                        ]
                    );
                } else {
                    $category_code = Record::mapCategory($cat);

                    if (!$category_code) {
                        info("unknown category, line:", [$cat, $line]);
                        throw new \Exception(
                            Lang::getFromJson(
                                "Unknown category ':category'. Check the sample CSV to see correct categories.",
                                ['category' => $cat]
                            )
                        );
                    }

                    //checking record type based on category to prevent opex uploads into cogs
                    if (self::isWrongCategory($category_code, $type)) {
                        $category = Record::getCategory($category_code);
                        info('wrong record category', [$category_code, $line]);
                        $errors[] = [
                            'message' => Lang::getFromJson(
                                "Category :category from :type_from is not present in :type_to. Row :row skipped.",
                                [
                                    'category' => $cat,
                                    'type_from' => $category['type'],
                                    'type_to' => $type,
                                    'row' => $row,
                                ]
                            ),
                        ];
                        continue;
                    }

                    $sub = Record::mapCategory($cat, true); // get sub category
                    if ($category_code != 'labor' && $category_code != 'subcontracting') {
                        $product = Product::GetByName($this->project->user, $name, false);
                    }
                }

                $record_data = [
                    'type' => $type,
                    'name' => $name,
                    'product_id' => $product ? $product->id : null,
                    'plan_id' => $plan ? $plan->id : null,
                    'category_code' => $category_code,
                    'planned' => array_get($this->record, 'planned', false),
                    'direct' => true,
                    'autofill' => null,
                ];

                $record = $this->project->records()->firstOrCreate($record_data);

                $meta = [
                    'datasource' => 'csv',
                    'datasource_id' => $this->id,
                ];
                // respect labor subcosts and opex subcategories
                if ($sub && $category_code == 'promotion') {
                    $meta['category'] = $sub;
                }

                $record->meta = array_merge(array_wrap($record->meta), $meta);
                $record->save();

                if ($this->planned) {
                    // create mirror actual record
                    $record_data['planned'] = false;
                    $actual = $this->project->records()->firstOrCreate($record_data);
                    $actual->meta = array_merge(array_wrap($actual->meta), $meta);
                    $actual->save();
                }

                logger()->debug("record:", [
                    $record->id,
                    $record->name,
                    $record->project->id,
                    $record->product_id,
                    $record->plan_id,
                    $record->created_at,
                ]);

                // TODO: think about more complex structure with different costs, quantity and additional meta data like division, contractor, etc...
                $date = date_parse_fixed(str_replace('/', '-', key($line)));
                // if no day is present in the date, or there is no year(indicates wrong date format) then it's monthly data
                $monthly = !$date['day'] || !$date['year'];
                logger()->debug("monthly:", [
                    $monthly,
                    array_only($date, ['year', 'month', 'day']),
                ]);

                if ($monthly) {
                    $result = $this->getMonthlyData($line);
                } else {
                    $result = $this->getDailyData($line);
                }
                if ($count = $this->saveTransactions($result[0], $record, $sub)) {
                    $this->records_count += $count;
                }
                $errors = array_merge($errors, $result[1]);
                // TODO: recalculate for all lines
                foreach ($result[2] as $adate) {
                    $record->recalculateDay($adate);
                    $record->recalculateMonth(as_date($adate)->format('Y-m'));
                }
            }
        } catch (\Exception $e) {
            logger()->error($e->getMessage(), [
                'file' => get_error_location($e),
            ]);
            return $this->statusError($e->getMessage());
        }
        $this->status = 'success';

        if (count($errors) > 0) {
            $this->status = 'warning';
            $this->meta = array_merge(array_wrap($this->meta), $errors);
        }
        $this->save();
        return true;
    }

    public function parseStripe()
    {
        if (!$this->webhook) {
            return $this->statusError('Webhook not found', true);
        }
        $payload = $this->webhook->payload;

        $amount = array_get($payload, 'data.object.amount_paid');
        $amount = $amount ?? array_get($payload, 'data.object.total', 0);
        $amount = $amount ?: array_get($payload, 'data.object.amount', 0);

        $contact = Contact::FindOrCreateFromStripe($this->project->user, $payload);

        if (!$contact) {
            return $this->statusError('Contact not saved', true);
        }
        debug('contact', [$contact->id]);

        $customer_id = array_get($payload, 'data.object.customer', '');
        $customer_id = $customer_id ?: array_get($payload, 'data.object.id');

        $payment_source = $contact->stripe($customer_id);
        if (!$payment_source) {
            info('no payment source', ['customer' => $customer_id, 'contact' => $contact->id]);
            return $this->statusError('no payment source');
        }
        $transaction_id = array_get($payload, 'data.object.charge');
        $transaction_id = $transaction_id ?: array_get($payload, 'data.object.id');

        if ($this->name == 'charge.refunded') {
            if (!$transaction_id) {
                info('refund without transaction id', $payload);
                return false;
            }
            $amount_refunded = array_get($payload, 'data.object.amount_refunded');
            info('got a refund', [$transaction_id, $amount_refunded]);
            $t = $this->project->transactions()
                ->where('remote_id', $transaction_id)
                ->first();
            if (!$t) {
                logger()->debug('transaction not found');
                return false;
            }
            $t->refunded = true;
            $t->update();
            // mark as deleted to hide from calculations
            // TODO: delete only if fully refunded
            $t->delete();
            $t->record->recalculateDay($t->date->format("Y-m-d"));
            $t->record->recalculateMonth($t->date->format("Y-m"));
            return true;
        }

        $invoices = array_get($payload, 'data.object.lines.data', []);
        debug('invoices in the payload', [count($invoices), $this->name]);
        if (!count($invoices)) {
            debug("no invoices in payload, nothing to add", []);
            return true;
        }

        $plan = Plan::FindOrCreateFromStripe($this->project, $invoices);
        debug('plan', [$plan->id, $plan->product_id]);

        $this->records_count = 0;
        $record_data = [
            'type' => 'revenue',
            'contact_id' => $contact->id,
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
            'name' => 'default', // channel
            'category_code' => 'revenue', // revenue will be grouped by product
            'planned' => 0,
            'direct' => true,
        ];
        $record = $this->project->records()->firstOrCreate($record_data);
        $record->meta = array_merge(array_wrap($record->meta), [
            'datasource' => 'stripe',
            'datasource_id' => $this->id,
            'description' => array_get($payload, 'data.object.description', ''),
            'discount' => array_get($payload, 'data.object.discount', ''),
        ]);
        $record->save();

        $date = array_get($payload, 'data.object.created', time());
        $date = as_date($date);
        debug('record', [$record->id, $amount, $date, $date->format("Y-m")]);

        // TODO: what about tax?
        $transaction = $record->saveTransaction([
            'remote_id' => $transaction_id,
            'source_id' => $this->id,
            'source_type' => self::class,
            'payment_source_id' => $payment_source->id,
            'price' => $amount,
            'quantity' => array_get($payload, 'data.object.quantity', 1),
            'date' => $date,
        ]);
        $transaction['total'] = $transaction['price'] * $transaction['quantity'];
        $this->records_count++;
        debug('transaction', [$transaction->id]);
        // update daily and monthly
        $record->recalculateDay($date->format("Y-m-d"));
        $record->recalculateMonth($date->format("Y-m"));
        $this->save();
        return true;
    }

    public function parseConnect()
    {
        if (!$this->webhook) {
            return $this->statusError('Webhook not found', true);
        }
        $payload = $this->webhook->payload;
        $transaction_id = array_get($payload, 'data.object.charge.id');
        $customer_id = array_get($this->meta, 'customer_id');
        $product_id = array_get($this->meta, 'product_id');
        $stripe_plan = array_get($this->meta, 'stripe_plan');

        $customer = Customer::where('id', $customer_id)->first();
        if (!$customer) {
            return $this->statusError('Customer not found', true);
        }
        $contact = $customer->contact;
        if (!$contact) {
            return $this->statusError('Contact not found', true);
        }
        $plan = Plan::where('payment_id', $stripe_plan)->first();
        if (!$plan) {
            return $this->statusError('Plan not found', true);
        }
        $payment_source = $contact->stripe($customer_id);
        if (!$payment_source) {
            $source = array_get($payload, 'data.object.change.source', []);
            $payment_source = $contact->payment_sources()->firstOrCreate([
                'remote_id' => $customer_id,
                'type' => 'stripe',
                'default' => true,
                'card_last_four' => array_get($source, 'last4'),
                'card_brand' => array_get($source, 'brand'),
                'meta' => [
                    'exp_year' => array_get($source, 'exp_year'),
                    'exp_month' => array_get($source, 'exp_month'),
                ],
            ]);
        }

        $this->records_count = 0;
        $record_data = [
            'type' => 'revenue',
            'contact_id' => $contact->id,
            'plan_id' => $plan->id,
            'product_id' => $product_id,
            'name' => 'default', // channel
            'category_code' => 'revenue', // revenue will be grouped by product
            'planned' => 0,
            'direct' => true,
        ];
        $record = $this->project->records()->firstOrCreate($record_data);
        $record->meta = array_merge(array_wrap($record->meta), [
            'datasource' => 'stripe',
            'datasource_id' => $this->id,
            'description' => array_get($payload, 'data.object.description', ''),
        ]);
        $record->save();

        $date = array_get($payload, 'data.object.created', time());
        $date = as_date($date);
        debug('record', [$record->id, $date, $date->format("Y-m")]);

        $transaction = $record->saveTransaction([
            'remote_id' => $transaction_id,
            'source_id' => $this->id,
            'source_type' => self::class,
            'payment_source_id' => $payment_source->id,
            'subtotal' => array_get($payload, 'data.object.subtotal'),
            'tax' => array_get($payload, 'data.object.tax'),
            'tax_percent' => array_get($payload, 'data.object.tax_percent'),
            'total' => array_get($payload, 'data.object.total'),
            'discount' => array_get($payload, 'data.object.discount', null),
            'quantity' => array_get($payload, 'data.object.subscription.quantity', 1),
            'date' => $date,
        ]);
        $this->records_count++;
        debug('transaction', [$transaction->id]);
        // update daily and monthly
        $record->recalculateDay($date->format("Y-m-d"));
        $record->recalculateMonth($date->format("Y-m"));
        $this->save();

        // save cost for application_fee_amount
        $name = 'Kinchaku Kickstart Fee';
        if ($this->project->user->language == 'ja') {
            // FIXME: maybe it's better to handle translation on the frontend?
            $name = 'Kinchaku Kickstart 手数料';
        }
        $record_data = [
            'type' => 'opex',
            'name' => $name,
            'category_code' => 'commissions',
            'planned' => false,
            'direct' => true,
        ];
        $record = $this->project->records()->firstOrCreate($record_data);
        $record->meta = array_merge(array_wrap($record->meta), [
            'service' => 'Kickstart',
        ]);

        if (!$record->product_id) {
            $product = Product::GetByName($this->project->user, 'Kinchaku', false);
            $record->product_id = $product->id;
        }
        $record->save();

        $record->saveTransaction([
            'remote_id' => $transaction_id,
            'source_id' => $this->id,
            'source_type' => self::class,
            'payment_source_id' => $payment_source->id,
            'subtotal' => array_get($payload, 'data.object.application_fee_amount'),
            'total' => array_get($payload, 'data.object.application_fee_amount'),
            'quantity' => 1,
            'date' => $date,
        ]);

        return true;
    }

    public function getMonthlyData($line)
    {
        $project_start = as_date($this->project->start_date, 'UTC');
        $data = [];
        $errors = [];
        $dates = [];
        foreach ($line as $key => $value) {
            $date = date_parse(str_replace('/', '-', $key));
            // if date_parse incorrectly parsed day as year (e.g. in Nov-18), fix it
            $date['year'] = $date['year'] ?: '20' . $date['day'];
            $chronos = Chronos::create($date['year'], $date['month'], 1, 0, 0, 0, 'UTC');
            if ($chronos->lt($project_start)) {
                logger()->debug('record date before project start', [$chronos, $key]);
                $errors[] = [
                    'message' => Lang::getFromJson(
                        "Skipped data for :date because it is before project start (:start).",
                        [
                            'date' => $key,
                            'start' => $project_start->format("Y/m/d"),
                        ]
                    ),
                ];
                continue;
            }
            // save transaction at the last day of the month
            $chronos = $chronos->addMonth()->subDay();
            $data[] = [
                'source_id' => $this->id,
                'source_type' => self::class,
                'price' => to_float($value),
                'quantity' => 1,
                'total' => to_float($value),
                'date' => $chronos,
            ];
            $dates[] = $chronos->format("Y-m-d");
        }
        $dates = array_unique($dates);
        return [$data, $errors, $dates];
    }

    public function getDailyData($line)
    {
        $project_start = as_date($this->project->start_date, 'UTC');
        $data = [];
        $errors = [];
        $dates = [];
        foreach ($line as $key => $value) {
            $date = str_replace('/', '-', $key);
            $date = as_date($date, 'UTC');
            if ($date->lt($project_start)) {
                logger()->debug('record date before project start', [$date, $key]);
                $errors[] = [
                    'message' => Lang::getFromJson(
                        "Skipped data for :date because it is before project start (:start).",
                        [
                            'date' => $key,
                            'start' => $project_start->format("Y/m/d"),
                        ]
                    ),
                ];
                continue;
            }

            $data[] = [
                'source_id' => $this->id,
                'source_type' => self::class,
                'price' => to_float($value),
                'quantity' => 1,
                'total' => to_float($value),
                'date' => $date,
            ];
            $dates[] = $date->format("Y-m-d");
        }
        $dates = array_unique($dates);
        return [$data, $errors, $dates];
    }

    public function saveTransactions($data, $record, $sub)
    {
        logger()->debug('saving transactions', ['count' => count($data)]);
        $count = 0;
        try {
            array_map(function ($c) use ($record, $sub, &$count) {
                if ($record->category_code != 'labor' || !$sub) {
                    $t = $record
                        ->transactions()
                        ->updateOrCreate(
                            ['date' => $c['date'], 'record_id' => $record->id],
                            $c
                        );
                    $count++;
                    return $t;
                }
                $amount = $c['price'];
                $c['price'] = 0;
                $t = $record
                    ->transactions()
                    ->firstOrCreate(['date' => $c['date'], 'record_id' => $record->id], $c);
                $t->meta = array_merge(array_wrap($t->meta), [$sub => $amount]);
                $t->save();
                $count++;
                return $t;
            }, $data);
        } catch (\Illuminate\Database\QueryException $e) {
            logger()->debug($e->getMessage());
            return $count;
        }
        return $count;
    }

    public static function isWrongCategory($code, $type)
    {
        $category = Record::getCategory($code);
        if ($type == 'revenue' && $type != $category['type']) {
            return true;
        }
        if ($category['type'] != 'any' && $type != $category['type']) {
            return true;
        }
        return false;
    }
}
