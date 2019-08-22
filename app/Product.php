<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cviebrock\EloquentTaggable\Taggable;
use Illuminate\Support\Facades\Lang;
use League\Csv\Reader;

class Product extends Model
{
    use Taggable, Traits\UuidModelTrait, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the user that added the data source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The projects that belong to the product.
     * @relation('BelongsToMany')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * Get product plans.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public static function import($path, $type = 'stripe')
    {
        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            //id,Name,Type,Date (UTC),Description,Statement Descriptor,Unit Label,Url
            $header = $csv->getHeader();
            logger()->debug('csv headers:', $header);

            $lines = $csv->getRecords();
            foreach ($lines as $row => $line) {
                $data = [];
                $data['sold'] = true;
                $data['payment_type'] = $type;
                $data['payment_id'] = $line['id'];
                $data['name'] = $line['Name'];
                $data['slug'] = str_slug_u($data['name']);
                $data['description'] = $line['Description'];
                $data['statement_descriptor'] = $line['Statement Descriptor'];
                $data['unit_label'] = $line['Unit Label'];
                $data['type'] = $line['Type'];
                $data['created_at'] = as_date($line['Date (UTC)']);
                // TODO: or maybe update by name?
                user()
                    ->products()
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

    public static function GetByName($user, $name, $sold = false)
    {
        return $user->firstOrCreateProduct(
            [
                'sold' => $sold,
                'slug' => str_slug_u($name),
            ],
            [
                'sold' => $sold,
                'name' => $name,
                'slug' => str_slug_u($name),
            ]
        );
    }
}
