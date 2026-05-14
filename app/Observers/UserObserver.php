<?php

namespace App\Observers;

use App\Enums\AttendanceStatus;
use App\Enums\UserStatus;
use App\Models\User;

class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('status') && $user->status === UserStatus::Inactive) {
            $user->eventAttendances()
                ->where('status', AttendanceStatus::Applied)
                ->update([
                    'status' => AttendanceStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
        }
    }
}
