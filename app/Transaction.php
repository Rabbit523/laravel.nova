<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nicolaslopezj\Searchable\SearchableTrait;

class Transaction extends Model
{
    use Traits\UuidModelTrait, SoftDeletes, SearchableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'remote_id',
        'source_id',
        'source_type',
        'payment_source_id',
        'price',
        'quantity',
        'refunded',
        'date',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'date'];

    /**
     * Searchable rules.
     *
     * @var array
     */
    protected $searchable = [
        'columns' => [
            'contacts.first_name' => 10,
            'contacts.last_name' => 10,
            'contacts.email' => 10,
            'transactions.date' => 5,
        ],
         'joins' => [
             'contacts' => ['records.contact_id', 'contacts.id']
         ],
    ];

    /**
     * Get the record definition that owns the transaction.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * Get the payment source that is the source for the transaction.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_source()
    {
        return $this->belongsTo(PaymentSource::class)->withDefault();
    }

    public function source()
    {
        // $result = DB::table('transaction_sources')
        //     ->select('source_id', 'source_type')
        //     ->where('transaction_id', '=', $this->id);
        // $model = $result->source_type;
        //
        // return $model::find($result->source_id);
        $model = $this->source_type;
        return $model::find($this->source_id);
    }

    /**
     * Parent transaction.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function refund()
    {
        return $this->belongsTo(Transaction::class, 'refund_id');
    }

    public function getTotal($price = null)
    {
        $result = is_null($price) ? $this->price * $this->quantity : $price;
        // FIXME: temporary solution. should grab all price fields and their quantities
        //"price","overtime","commuting","welfare","bonus","retirement"
        $result +=
            array_get($this->meta, 'overtime', 0) +
            array_get($this->meta, 'commuting', 0) +
            array_get($this->meta, 'welfare', 0) +
            array_get($this->meta, 'bonus', 0) +
            array_get($this->meta, 'retirement', 0);
        return $result;
    }
}
