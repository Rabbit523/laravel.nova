<?php
namespace App\Filters\Contact;

use Fouladgar\EloquentBuilder\Support\Foundation\Contracts\IFilter as Filter;
use Illuminate\Database\Eloquent\Builder;

class LastVisitAtFilter implements Filter
{
    /**
     * Apply the age condition to the query.
     *
     * @param Builder $builder
     * @param mixed   $value
     *
     * @return Builder
     */
    public function apply(Builder $builder, $value): Builder
    {
        if (!empty($value['lt'])) {
            return $builder->where('last_contact_at', '<', $value['lt']);
        }

        return $builder->where('last_contact_at', '>',  $value['gt']);
    }
}
