<?php

namespace App\Notifications;

use App\User;
use Parsedown;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    public $table = 'notifications';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The guarded attributes on the model.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'read_at' => 'datetime',
        'failed' => 'boolean'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['parsed_body'];

    /**
     * Set the message icon.
     *
     * @param  string  $icon
     * @return $this
     */
    public function icon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the message body.
     *
     * @param  string  $body
     * @return $this
     */
    public function body($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the message author.
     *
     * @param  User  $from
     * @return $this
     */
    public function from(User $from)
    {
        $this->creator = $from;

        return $this;
    }

    /**
     * Set the button text.
     *
     * @param  string  $text
     * @param  string  $url
     * @return $this
     */
    public function action($text, $url)
    {
        $this->actionText = $text;
        $this->actionUrl = $url;

        return $this;
    }

    /**
     * Get the user the notification belongs to.
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    /**
     * Get the user that created the notification (if any).
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get the parsed body of the announcement.
     *
     * @return string
     */
    public function getParsedBodyAttribute()
    {
        return (new Parsedown())->text(htmlspecialchars($this->attributes['body']));
    }

    /**
     * Mark the notification as read.
     *
     * @return void
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * Mark the notification as unread.
     *
     * @return void
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Determine if a notification has been read.
     *
     * @return bool
     */
    public function read()
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if a notification has not been read.
     *
     * @return bool
     */
    public function unread()
    {
        return $this->read_at === null;
    }
    //
    // /**
    //  * Create a new database notification collection instance.
    //  *
    //  * @param  array  $models
    //  * @return \Illuminate\Notifications\DatabaseNotificationCollection
    //  */
    // public function newCollection(array $models = [])
    // {
    //     return new DatabaseNotificationCollection($models);
    // }
}
