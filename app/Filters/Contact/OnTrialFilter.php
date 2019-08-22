<?php
namespace App\Filters\Contact;

use Fouladgar\EloquentBuilder\Support\Foundation\Contracts\IFilter as Filter;
use Illuminate\Database\Eloquent\Builder;

class OnTrialFilter implements Filter
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
        if ($value) {
            return $builder->whereHas('customers', function ($q) {
                $q->whereNotNull('trial_ends_at')
                    ->where('trial_ends_at', '>', now());
            });
        }

        return $builder->whereHas('customers', function ($q) {
            $q->whereNull('trial_ends_at');
        });
    }
}
