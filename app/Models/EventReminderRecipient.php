<?php

namespace App\Models;

use App\Enums\ReminderRecipientStatus;
use Database\Factories\EventReminderRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_reminder_id',
    'user_id',
    'email',
    'status',
    'error',
    'sent_at',
])]
class EventReminderRecipient extends Model
{
    /** @use HasFactory<EventReminderRecipientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReminderRecipientStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventReminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(EventReminder::class, 'event_reminder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
