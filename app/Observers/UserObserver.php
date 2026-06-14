<?php

namespace App\Observers;

use App\Enums\AttendanceStatus;
use App\Enums\UserStatus;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventOwnershipService;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly EventOwnershipService $eventOwnershipService,
    ) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged('status') && $user->status === UserStatus::Inactive) {
            $this->syncCalendarOnDeactivation($user);
            $this->eventOwnershipService->handleOwnerDeactivation($user);

            $user->eventAttendances()
                ->where('status', AttendanceStatus::Applied)
                ->update([
                    'status' => AttendanceStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
        }
    }

    private function syncCalendarOnDeactivation(User $user): void
    {
        if (! $user->hasGoogleCalendarConnected()) {
            return;
        }

        $user->eventAttendances()
            ->where('status', AttendanceStatus::Applied)
            ->whereNotNull('google_calendar_event_id')
            ->each(function (EventAttendance $attendance) use ($user): void {
                try {
                    $this->googleCalendarService->deleteEvent($user, (string) $attendance->google_calendar_event_id);
                    $attendance->update(['google_calendar_event_id' => null]);
                } catch (\Throwable $e) {
                    Log::warning('退会時のGoogleカレンダー削除に失敗', [
                        'user_id' => $user->id,
                        'attendance_id' => $attendance->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        $this->googleCalendarService->revoke($user);
        $user->googleCalendarToken()->delete();
    }
}
