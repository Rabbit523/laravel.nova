<?php
namespace App\Traits;

use App\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAddresses
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'model');
    }

    /**
     * @param string $role
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function address(string $role, $address = null): ?Model
    {
        if (is_array($address)) {
            $address = $this->addresses()->create($address);
        }

        if ($address instanceof Model) {
            $address->role($role);
        }

        return $this->addresses()
            ->whereRole($role)
            ->first();
    }

    /**
     * @param string $role
     *
     * @return bool
     */
    public function hasAddress(string $role): bool
    {
        return !empty($this->address($role));
    }
}
