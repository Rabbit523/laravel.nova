<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\UuidModelTrait;
use Illuminate\Notifications\Notifiable;

class Team extends Model
{
    use Traits\UuidModelTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * Get the user that owns the team.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The members that belong to the team.
     */
    public function members()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The projects that belong to the team.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = []; //'members', 'projects'
}
