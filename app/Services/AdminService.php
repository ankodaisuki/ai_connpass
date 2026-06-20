<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\UserStatus;
use App\Mail\AdminEventDeletedMail;
use App\Models\AdminAuditLog;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminService
{
    public function freezeUser(User $target, User $admin, string $reason): void
    {
        DB::transaction(function () use ($target, $admin, $reason) {
            $target->update([
                'status' => UserStatus::Frozen,
                'frozen_reason' => $reason,
            ]);

            AdminAuditLog::create([
                'admin_user_id' => $admin->id,
                'action' => 'freeze',
                'target_type' => 'user',
                'target_id' => $target->id,
                'reason' => $reason,
            ]);
        });
    }

    public function unfreezeUser(User $target, User $admin, string $reason): void
    {
        DB::transaction(function () use ($target, $admin, $reason) {
            $target->update([
                'status' => UserStatus::Active,
                'frozen_reason' => null,
            ]);

            AdminAuditLog::create([
                'admin_user_id' => $admin->id,
                'action' => 'unfreeze',
                'target_type' => 'user',
                'target_id' => $target->id,
                'reason' => $reason,
            ]);
        });
    }

    public function deleteEvent(Event $event, User $admin, string $reason): void
    {
        $attendees = $event->attendances()
            ->with('user')
            ->where('status', AttendanceStatus::Applied)
            ->get()
            ->map(fn (EventAttendance $a) => $a->user);

        DB::transaction(function () use ($event, $admin, $reason) {
            $event->update(['status' => EventStatus::Private]);
            $event->delete();

            AdminAuditLog::create([
                'admin_user_id' => $admin->id,
                'action' => 'delete_event',
                'target_type' => 'event',
                'target_id' => $event->id,
                'reason' => $reason,
            ]);
        });

        foreach ($attendees as $attendee) {
            try {
                Mail::to($attendee->email)->send(new AdminEventDeletedMail($event, $reason));
            } catch (\Throwable $e) {
                Log::warning('運営削除通知メール送信に失敗', [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
