<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UuidModelTrait
{
    public function getKeyType()
    {
        return "string";
    }
    /*
     * This function is used internally by Eloquent models to test if the model has auto increment value
     * @returns bool Always false
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * This function overwrites the default boot static method of Eloquent models. It will hook
     * the creation event with a simple closure to insert the UUID
     */
    public static function bootUuidModelTrait()
    {
        static::creating(function ($model) {
            // Only generate UUID if it wasn't set by already.
            if (!isset($model->attributes[$model->getKeyName()])) {
                // This is necessary because on \Illuminate\Database\Eloquent\Model::performInsert
                // will not check for $this->getIncrementing() but directly for $this->incrementing
                $model->incrementing = false;

                $model->attributes[$model->getKeyName()] = Str::orderedUuid()->toString();
            }
        });
    }
}
