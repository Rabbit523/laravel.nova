<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cake\Chronos\Chronos;

// may be a better idea is to create map between our categories and whatever user entered
const CATEGORIES = [
    "labor" => [
        'en' => 'labor',
        'ja' => '人件費',
        'type' => 'any',
        'sub' => [
            "welfare" => ['en' => 'statutory welfare expenses', 'ja' => '法定福利費'],
            'price' => ['en' => 'base salary', 'ja' => '基本給'],
            'bonus' => ['en' => 'bonuses & incentive pays', 'ja' => '賞与引当金繰入額'],
            'overtime' => ['en' => 'overtime pay', 'ja' => '残業手当'],
            'commuting' => ['en' => 'commuting allowance', 'ja' => '通勤手当'],
            'retirement' => ['en' => 'retirement pay', 'ja' => '退職給付引当金繰入額'],
        ],
    ],
    "subcontracting" => [
        'en' => 'subcontracting & outsourcing expenses',
        'ja' => '外注加工費',
        'type' => 'any',
    ],
    "raw_materials_parts" => [
        'en' => 'raw materials & parts',
        'ja' => '原材料及び貯蔵品',
        'type' => 'cogs',
    ],
    "tools_equipment" => [
        'en' => "tools & equipment",
        'ja' => '工具器具備品',
        'type' => 'cogs',
    ],
    "communication" => ['en' => "communication expenses", 'ja' => '通信費', 'type' => 'opex'],
    "internet" => [
        'en' => "internet related expenses",
        'ja' => 'インターネット関連費',
        'type' => 'any',
    ],
    "professional_services" => [
        'en' => "professional services",
        'ja' => '支払報酬料',
        'type' => 'any',
    ],
    "rent" => ['en' => "rent", 'ja' => '地代家賃', 'type' => 'any'],
    "utility" => ['en' => "utility expenses", 'ja' => '水道光熱費', 'type' => 'any'],
    "consumables" => ['en' => "consumables", 'ja' => '消耗品費', 'type' => 'any'],
    "delivery" => ['en' => "packing & delivery expenses", 'ja' => '荷造運賃', 'type' => 'any'],
    "duties" => ['en' => "custom taxes & duties", 'ja' => '関税・輸入消費税', 'type' => 'any'],
    "depreciation" => ['en' => "depreciation expenses", 'ja' => '減価償却費', 'type' => 'any'],
    "travel" => [
        'en' => "travel & accommodation expenses",
        'ja' => '旅費交通費',
        'type' => 'any',
    ],
    "maintenance" => ['en' => "maintenance expenses", 'ja' => '修繕費', 'type' => 'any'],
    "commissions" => ['en' => "commissions", 'ja' => '支払手数料', 'type' => 'opex'],
    "promotion" => [
        'en' => "ads & promotion expenses",
        'ja' => "広告宣伝費",
        'type' => 'opex',
        'sub' => [
            "online" => ['en' => 'online advertising', 'ja' => 'オンライン広告'],
            "offline" => ['en' => "offline advertising", 'ja' => 'オフライン広告'],
            "pr" => ['en' => "public relations", 'ja' => '広報'],
            "sns" => ['en' => "social media", 'ja' => 'sns'],
            "seo" => ['en' => "seo", 'ja' => 'seo'],
            "research" => ['en' => "market research", 'ja' => 'マーケットリサーチ'],
            "email" => ['en' => "email marketing", 'ja' => 'メールマーケティング'],
            "campaign" => ['en' => "sales campaign", 'ja' => '販売キャンペーン'],
            "other" => ['en' => "other", 'ja' => 'その他'],
        ],
    ],
    "revenue" => ['en' => "revenue", 'ja' => '収益', 'type' => 'revenue'],
    "initial" => ['en' => "Initial Cost", 'ja' => "ローンチ費用", 'type' => 'cogs'],
];

class Record extends Model
{
    use Traits\UuidModelTrait, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contact_id',
        'product_id',
        'plan_id',
        'category_id',
        'category_code',
        'name',
        'direct',
        'planned',
        'type',
        'autofill',
        'meta',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'meta' => 'array',
        'autofill' => 'array',
        'direct' => 'boolean',
        'planned' => 'boolean',
    ];

    /**
     * Get the project that owns the record.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class)->withDefault();
    }

    /**
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class)
            ->withTrashed()
            ->withDefault();
    }

    /**
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class)
            ->withTrashed()
            ->withDefault();
    }

    /**
     * Get transactions.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get monthly records.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function monthly()
    {
        return $this->hasMany(MonthlyRecord::class);
    }

    /**
     * Get daily records.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function daily()
    {
        return $this->hasMany(DailyRecord::class);
    }

    /**
     * Scope a query to only include cogs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCogs($query)
    {
        return $query->where('type', 'cogs');
    }

    /**
     * Scope a query to only include opex.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpex($query)
    {
        return $query->where('type', 'opex');
    }

    /**
     * Scope a query to only include launch.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLaunch($query)
    {
        return $query->where('type', 'launch');
    }

    /**
     * Scope a query to only include revenue.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRevenue($query)
    {
        return $query->where('type', 'revenue');
    }

    /**
     * Scope a query to only include actual records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActual($query)
    {
        return $query->where('planned', false);
    }

    /**
     * Scope a query to only include planned records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePlanned($query)
    {
        return $query->where('planned', true);
    }

    public function autoFill(int $duration = null)
    {
        if (
            !array_has($this->autofill, 'start_from') ||
            !array_has($this->autofill, 'price')
        ) {
            logger()->debug("nothing to autofill", [$this->autofill]);
            return;
        }

        $start_from = array_get($this->autofill, 'start_from', 0);
        $duration = $duration ?? abs(array_get($this->autofill, 'duration', 1));
        $length = $duration + $start_from;

        // TODO: add ability to autofill by multiple periods
        $this->monthly()->delete();
        $this->daily()->delete();
        // FIXME: Very destructive, should we disallow it if we have transactions that come from integrations?
        $this->transactions()->delete();

        $data = [];
        $i = $start_from;
        $dates = [];
        while ($i < $length) {
            $date = as_date($this->project->start_date, 'UTC')
                ->addMonth($i + 1)
                ->subDay();
            $data[] = [
                'date' => $date,
                'source_id' => auth()->id(),
                'source_type' => User::class,
                'price' => array_get($this->autofill, 'price'),
                'quantity' => array_get($this->autofill, 'quantity'),
                'meta' => $this->getPriceFields(),
            ];
            $i++;
            $dates[] = $date->format("Y-m-d");
        }

        $this->transactions()->saveMany(
            array_map(function ($t) {
                return new Transaction($t);
            }, $data)
        );

        if ($length > $this->project->duration) {
            $this->project->duration = $length;
            $this->project->save();
        }

        $dates = array_unique($dates);
        foreach ($dates as $date) {
            $this->recalculateDay($date);
            $this->recalculateMonth(as_date($date)->format('Y-m'));
        }
    }

    public function transactionsForDay($date)
    {
        return $this->transactions()
            ->whereRaw('DATE(date) = ?', [$date])
            ->get();
    }

    public function transactionsForMonth($date)
    {
        $date = date_parse($date);
        $from = Chronos::create($date['year'], $date['month'], 1, 0, 0, 0, 'UTC');
        $to = Chronos::create($date['year'], $date['month'], 1, 0, 0, 0, 'UTC');
        $to = $to->addMonth()->subDay();

        return $this->transactions()
            ->whereBetween('date', [$from->format("Y-m-d"), $to->format("Y-m-d")])
            ->get();
    }

    public function recalculateDay($date)
    {
        $category = $this->category_code;
        $value = $this->transactionsForDay($date)->reduce(function ($carry, $item) use (
            $category
        ) {
            if ($category != 'labor') {
                return $carry += $item->getTotal();
            }
            $carry = $carry
                ?: [
                    'price' => 0,
                    'meta' => [
                        'overtime' => 0,
                        'commuting' => 0,
                        'welfare' => 0,
                        'bonus' => 0,
                        'retirement' => 0,
                    ],
                ];
            $carry['price'] += $item->price * $item->quantity;
            if (array_has($item->meta, 'overtime')) {
                $carry['meta']['overtime'] += array_get($item->meta, 'overtime', 0);
                $carry['meta']['commuting'] += array_get($item->meta, 'commuting', 0);
                $carry['meta']['welfare'] += array_get($item->meta, 'welfare', 0);
                $carry['meta']['bonus'] += array_get($item->meta, 'bonus', 0);
                $carry['meta']['retirement'] += array_get($item->meta, 'retirement', 0);
            }
            return $carry;
        },
        0);

        logger()->debug("recalculateDay", [$this->id, $date, $value]);

        $price = is_array($value) ? $value['price'] : $value;
        $meta = is_array($value) ? $value['meta'] : [];

        try {
            $this->daily()->updateOrCreate(
                ['date' => $date, 'record_id' => $this->id],
                [
                    'price' => to_float($price),
                    'quantity' => 1,
                    'date' => $date,
                    'meta' => $meta,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            info($e->getMessage());
        }
    }

    public function recalculateMonth($date)
    {
        $category = $this->category_code;
        $value = $this->transactionsForMonth($date)->reduce(function ($carry, $item) use (
            $category
        ) {
            // labor is an exception, we need to calculate meta costs separately and store
            // them in monthly record
            if ($category != 'labor') {
                return $carry += $item->getTotal();
            }
            $carry = $carry
                ?: [
                    'price' => 0,
                    'meta' => [
                        'overtime' => 0,
                        'commuting' => 0,
                        'welfare' => 0,
                        'bonus' => 0,
                        'retirement' => 0,
                    ],
                ];
            $carry['price'] += $item->price * $item->quantity;
            if (array_has($item->meta, 'overtime')) {
                $carry['meta']['overtime'] += array_get($item->meta, 'overtime', 0);
                $carry['meta']['commuting'] += array_get($item->meta, 'commuting', 0);
                $carry['meta']['welfare'] += array_get($item->meta, 'welfare', 0);
                $carry['meta']['bonus'] += array_get($item->meta, 'bonus', 0);
                $carry['meta']['retirement'] += array_get($item->meta, 'retirement', 0);
            }
            return $carry;
        },
        0);
        $fulldate = $date . '-1';
        logger()->debug("recalculateMonth", [$this->id, $date, $fulldate, $value]);
        $price = is_array($value) ? $value['price'] : $value;
        $meta = is_array($value) ? $value['meta'] : [];

        try {
            $this->monthly()->updateOrCreate(
                ['date' => $fulldate, 'record_id' => $this->id],
                [
                    'price' => to_float($price),
                    'quantity' => 1,
                    'date' => $fulldate,
                    'meta' => $meta,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            info($e->getMessage());
        }
    }

    public function getTotal($price = null)
    {
        $result = is_null($price) ? $this->price * $this->quantity : $price;
        // FIXME: temporary solution. should grab all price fields and their quantities
        //"price","overtime","commuting","welfare","bonus","retirement"
        if (array_has($this->meta, 'overtime')) {
            $result +=
                array_get($this->meta, 'overtime', 0) +
                array_get($this->meta, 'commuting', 0) +
                array_get($this->meta, 'welfare', 0) +
                array_get($this->meta, 'bonus', 0) +
                array_get($this->meta, 'retirement', 0);
        }
        return $result;
    }

    public function getPriceFields()
    {
        if ($this->category_code != 'labor') {
            return [];
        }
        return [
            'overtime' => array_get($this->meta, 'overtime', 0),
            'commuting' => array_get($this->meta, 'commuting', 0),
            'welfare' => array_get($this->meta, 'welfare', 0),
            'bonus' => array_get($this->meta, 'bonus', 0),
            'retirement' => array_get($this->meta, 'retirement', 0),
        ];
    }

    public function saveTransaction($data)
    {
        // if we have remote transaction id, use it to check for existing record
        if (array_has($data, 'remote_id')) {
            return $this->transactions()->firstOrCreate(
                ['remote_id' => $data['remote_id']],
                $data
            );
        }
        return $this->transactions()->firstOrCreate($data);
    }

    public static function mapCategory($category, $return_key = false)
    {
        $category = strtolower($category);
        $key = searchCategories(CATEGORIES, $category);
        if ($key !== false) {
            return $return_key ? false : $key;
        }
        $temp = searchCategories(CATEGORIES['promotion']['sub'], $category);
        if ($temp !== false) {
            return $return_key ? $temp : 'promotion';
        }
        $temp = searchCategories(CATEGORIES['labor']['sub'], $category);
        if ($temp !== false) {
            return $return_key ? $temp : 'labor';
        }
        return false;
    }

    public static function getCategory($code)
    {
        return isset(CATEGORIES[$code]) ? CATEGORIES[$code] : false;
    }

    public static function getCategoryName($code, $lang)
    {
        return ($category = self::getCategory($code)) ? $category[$lang] : 'unknown';
    }
}

function searchCategories($categories, $category)
{
    $key = array_search($category, array_column($categories, 'en'));
    if ($key === false) {
        $key = array_search($category, array_column($categories, 'ja'));
    }
    if ($key !== false) {
        return array_keys($categories)[$key];
    }
    return $key;
}
