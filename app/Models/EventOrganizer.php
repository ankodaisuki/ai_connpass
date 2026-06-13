<?php

namespace App\Models;

use App\Enums\OrganizerInvitationStatus;
use Database\Factories\EventOrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'user_id',
    'status',
    'invited_at',
    'responded_at',
])]
class EventOrganizer extends Model
{
    /** @use HasFactory<EventOrganizerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizerInvitationStatus::class,
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * 紐づくイベント
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 招待された合同主催者
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
