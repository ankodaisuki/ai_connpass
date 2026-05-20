<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\EventAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'event_id',
    'user_id',
    'status',
    'applied_at',
    'cancelled_at',
    'attended_at',
])]
class EventAttendance extends Model
{
    /** @use HasFactory<EventAttendanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attended_at' => 'datetime',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * 申し込み対象のイベント
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 申し込みを行ったユーザー
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
