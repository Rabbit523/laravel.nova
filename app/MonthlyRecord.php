<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MonthlyRecord extends Model
{
    use Traits\HasCompositePrimaryKeyTrait;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'monthly_records';

    /**
     * The primary key of the table.
     *
     * @var array
     */
    protected $primaryKey = ['record_id', 'date'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['price', 'quantity', 'date', 'meta'];

    public $timestamps = false;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['date'];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * Get the record definition.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function record()
    {
        return $this->belongsTo(Record::class);
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
