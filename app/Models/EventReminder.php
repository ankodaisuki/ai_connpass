<?php

namespace App\Models;

use Database\Factories\EventReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'sent_by_user_id',
    'subject',
    'body',
    'total_count',
    'sent_count',
    'failed_count',
])]
class EventReminder extends Model
{
    /** @use HasFactory<EventReminderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return HasMany<EventReminderRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EventReminderRecipient::class);
    }
}
