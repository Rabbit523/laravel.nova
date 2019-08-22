<?php

namespace App\Policies;

use App\User;
use App\Record;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecordPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the record.
     *
     * @param  \App\User  $user
     * @param  \App\Record  $record
     * @return mixed
     */
    public function view(User $user, Record $record)
    {
        return true;
    }

    /**
     * Determine whether the user can create records.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->acl >= 10;
    }

    /**
     * Determine whether the user can update the record.
     *
     * @param  \App\User  $user
     * @param  \App\Record  $record
     * @return mixed
     */
    public function update(User $user, Record $record)
    {
        return $user->acl >= 10;
    }

    /**
     * Determine whether the user can delete the record.
     *
     * @param  \App\User  $user
     * @param  \App\Record  $record
     * @return mixed
     */
    public function delete(User $user, Record $record)
    {
        return $user->acl >= 10;
    }

    /**
     * Determine whether the user can restore the record.
     *
     * @param  \App\User  $user
     * @param  \App\Record  $record
     * @return mixed
     */
    public function restore(User $user, Record $record)
    {
        return $user->acl >= 10;
    }

    /**
     * Determine whether the user can permanently delete the record.
     *
     * @param  \App\User  $user
     * @param  \App\Record  $record
     * @return mixed
     */
    public function forceDelete(User $user, Record $record)
    {
        return $user->acl >= 11;
    }
}
