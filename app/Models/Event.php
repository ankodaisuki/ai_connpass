<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'title',
    'description',
    'category',
    'prefecture',
    'location',
    'event_date',
    'end_date',
    'capacity',
    'status',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'end_date' => 'datetime',
            'category' => EventCategory::class,
            'status' => EventStatus::class,
        ];
    }

    /**
     * イベント作成者
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * イベント申し込み一覧（全ステータス）
     *
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * 申し込み中（Applied）の参加者一覧
     *
     * @return HasMany<EventAttendance, $this>
     */
    public function appliedAttendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class)
            ->where('status', AttendanceStatus::Applied);
    }
}
