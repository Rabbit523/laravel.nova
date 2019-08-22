<?php
namespace App\Filters\Contact;

use Fouladgar\EloquentBuilder\Support\Foundation\Contracts\IFilter as Filter;
use Illuminate\Database\Eloquent\Builder;

class TrialEndsAtFilter implements Filter
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
            return $builder->whereHas('customers', function($q) use ($value){
                $q->whereNotNull('trial_ends_at')
                    ->where('trial_ends_at', '<',  $value['lt']);
            });
        }

        return $builder->whereHas('customers', function($q) use ($value){
            $q->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>',  $value['gt']);
        });
    }
}
