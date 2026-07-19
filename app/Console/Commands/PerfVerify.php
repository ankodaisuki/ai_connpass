<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Console\Command;

class PerfVerify extends Command
{
    protected $signature = 'perf:verify {event : 対象イベントID}';

    protected $description = '性能試験後に定員・キャンセル待ちの整合性を検証する（違反があれば exit 1）';

    public function handle(): int
    {
        $event = Event::withTrashed()->findOrFail((int) $this->argument('event'));

        $applied = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();
        $waitlisted = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->count();

        $this->table(
            ['項目', '値'],
            [
                ['定員', $event->capacity],
                ['参加確定（Applied）', $applied],
                ['キャンセル待ち（Waitlisted）', $waitlisted],
            ],
        );

        $violations = [];
        if ($applied > $event->capacity) {
            $violations[] = "定員超過: 参加確定 {$applied} 件 > 定員 {$event->capacity}";
        }
        if ($applied < $event->capacity && $waitlisted > 0) {
            $violations[] = '繰り上げ漏れ: 空席 '.($event->capacity - $applied)." があるのにキャンセル待ち {$waitlisted} 件が残存";
        }

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }

            return self::FAILURE;
        }

        $this->info('整合性OK');

        return self::SUCCESS;
    }
}
