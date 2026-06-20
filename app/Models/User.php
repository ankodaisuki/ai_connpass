<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'name', 'password', 'status', 'is_admin', 'frozen_reason'])]
#[Hidden(['password'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isFrozen(): bool
    {
        return $this->status === UserStatus::Frozen;
    }

    /**
     * このユーザーが作成したイベント一覧
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * このユーザーのイベント申し込み一覧
     *
     * @return HasMany<EventAttendance, $this>
     */
    public function eventAttendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * @return HasOne<GoogleCalendarToken, $this>
     */
    public function googleCalendarToken(): HasOne
    {
        return $this->hasOne(GoogleCalendarToken::class);
    }

    public function hasGoogleCalendarConnected(): bool
    {
        return $this->googleCalendarToken()->exists();
    }

    /**
     * このユーザー宛ての合同主催の招待一覧
     *
     * @return HasMany<EventCoOrganizer, $this>
     */
    public function organizerInvitations(): HasMany
    {
        return $this->hasMany(EventCoOrganizer::class);
    }
}
