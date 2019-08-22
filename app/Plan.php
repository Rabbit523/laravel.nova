<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;
use League\Csv\Reader;

class Plan extends Model
{
    use Traits\UuidModelTrait, SoftDeletes;

    protected $guarded = ['id', 'product_id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'tax_included',
        'addons',
        'interval',
        'payment_id',
        'payment_type',
        'currency',
        'amount',
        'interval_count',
        'trial_days',
        'billing_day',
        'billing_scheme',
        'meta',
        'created_at',
        'managed',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'meta' => 'array',
        'addons' => 'array',
        'tax_included' => 'boolean',
    ];
    /**
     * Get the product that owns the plan.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public static function import($path, $type = 'stripe')
    {
        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            //Plan ID,Product ID,Product Name,Product Statement Descriptor,Nickname,Created (UTC),Amount,
            //Currency,Interval,Interval Count,Usage Type,Aggregate Usage,Billing Scheme,Trial Period Days
            $header = $csv->getHeader();
            logger()->debug('csv headers:', $header);

            $lines = $csv->getRecords();

            foreach ($lines as $row => $line) {
                $data = [];
                $data['payment_type'] = $type;
                $data['payment_id'] = $line['Plan ID'];
                $product_id = $line['Product ID'];
                if (!$product_id || !$data['payment_id']) {
                    throw new Exception("No plan or product id found");
                }

                $data['name'] = $line['Nickname'];
                $data['slug'] = str_slug_u($data['name']);
                $data['amount'] = $line['Amount'];
                $data['interval'] = $line['Interval'];
                $data['currency'] = $line['Currency'];
                $data['interval_count'] = $line['Interval Count'];
                $data['trial_days'] = $line['Trial Period Days'];
                $data['billing_scheme'] = $line['Billing Scheme'];
                $data['created_at'] = as_date($line['Created (UTC)']);
                logger()->debug('line', [$line, $data]);

                $product = user()
                    ->products()
                    ->where('payment_id', $product_id)
                    ->where('payment_type', $type)
                    ->first();

                if (!$product) {
                    $product = user()
                        ->products()
                        ->create([
                            'payment_id' => $product_id,
                            'payment_type' => 'stripe',
                            'name' => $line['Product Name'],
                            'slug' => str_slug($line['Product Name']),
                            'sold' => true,
                            'statement_descriptor' => array_get(
                                $line,
                                'Product Statement Descriptor'
                            ),
                            'created_at' => as_date($line['Created (UTC)']),
                        ]);
                }
                $product
                    ->plans()
                    ->updateOrCreate(
                        ['payment_id' => $data['payment_id'], 'payment_type' => $type],
                        $data
                    );
            }
        } catch (\Exception $e) {
            logger()->error($e->getMessage(), [
                'file' => get_error_location($e),
            ]);
            throw new \Exception(Lang::getFromJson("CSV import failed"));
        }
        return true;
    }

    public static function FindOrCreateFromStripe($project, $invoices)
    {
        if (!count($invoices)) {
            return false;
        }
        // FIXME: for now take only first invoice
        $invoice = array_filter(current($invoices));
        $plan_data = array_filter(array_get($invoice, 'plan', []));
        logger()->debug('plan', $plan_data);
        if (!$plan_data) {
            info('no plans in invoice', $invoice);
            // or check for product?
            return false;
        }
        $product_id = $plan_data['product'];
        $product = $project
            ->products()
            ->where('payment_id', $product_id)
            ->where('payment_type', 'stripe')
            ->first();

        $plan_data['payment_id'] = $plan_data['id'];
        $plan_data['payment_type'] = 'stripe';
        $plan_data['created_at'] = as_date($plan_data['created']);
        $plan_data['name'] = array_get($plan_data, 'nickname', $plan_data['id']);
        $plan_data['trial_days'] = array_get($plan_data, 'trial_period_days', 0);
        unset(
            $plan_data['id'],
            $plan_data['object'],
            $plan_data['active'],
            $plan_data['created'],
            $plan_data['nickname'],
            $plan_data['product'],
            $plan_data['trial_period_days'],
            $plan_data['usage_type']
        );

        if (!$product) {
            $product = $project->user->products()->create([
                'name' => 'New stripe product',
                'sold' => true,
                'slug' => str_slug('New stripe product'),
                'payment_id' => $product_id,
                'payment_type' => 'stripe',
            ]);
            $project->products()->attach($product);
            $plan = $product->plans()->create($plan_data);
        } else {
            $plan = $product
                ->plans()
                ->where('payment_id', $plan_data['payment_id'])
                ->first();
            if (!$plan) {
                $plan = $product->plans()->create($plan_data);
                $product->projects()->syncWithoutDetaching([$project->id]);
            }
        }
        return $plan;
    }
}
