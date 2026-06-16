<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Enums\OrganizerInvitationStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'title',
    'description',
    'category',
    'prefecture',
    'location',
    'online_url',
    'online_password',
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

    /**
     * キャンセル待ち（Waitlisted）の参加者一覧
     *
     * @return HasMany<EventAttendance, $this>
     */
    public function waitlistAttendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class)
            ->where('status', AttendanceStatus::Waitlisted);
    }

    /**
     * このイベントの合同主催者の招待レコード（全状態）
     *
     * @return HasMany<EventCoOrganizer, $this>
     */
    public function eventCoOrganizers(): HasMany
    {
        return $this->hasMany(EventCoOrganizer::class);
    }

    /**
     * 承諾済みの合同主催者（公開表示・権限判定に使用）
     *
     * @return BelongsToMany<User, $this>
     */
    public function acceptedCoOrganizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_co_organizers')
            ->wherePivot('status', OrganizerInvitationStatus::Accepted->value)
            ->withPivot(['status', 'invited_at', 'responded_at'])
            ->withTimestamps();
    }

    /**
     * このイベントのリマインド配信ヘッダ一覧
     *
     * @return HasMany<EventReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }

    /**
     * 指定ユーザーがこのイベントのオーナー（作成者）か
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * 指定ユーザーが承諾済みの合同主催者か
     */
    public function isAcceptedCoOrganizer(User $user): bool
    {
        return $this->eventCoOrganizers()
            ->where('user_id', $user->id)
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->exists();
    }

    /**
     * 指定ユーザーが主催者（オーナー or 承諾済み合同主催者）か
     */
    public function isOrganizer(User $user): bool
    {
        return $this->isOwner($user) || $this->isAcceptedCoOrganizer($user);
    }
}
