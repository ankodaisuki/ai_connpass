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

#[Fillable(['email', 'name', 'password', 'status'])]
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
        ];
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
}
