<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EventService
{
    /**
     * フィルタ条件を適用したイベント一覧クエリを返す
     *
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        $query = Event::query()
            ->with('user')
            ->withCount('appliedAttendances as attendances_count')
            ->where('status', EventStatus::Published)
            ->where('event_date', '>=', now());

        if ($q = ($filters['q'] ?? null)) {
            $query->where(function (Builder $qb) use ($q) {
                $qb->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        if ($category = ($filters['category'] ?? null)) {
            $query->where('category', EventCategory::from((int) $category));
        }

        if ($prefecture = ($filters['prefecture'] ?? null)) {
            $query->where('prefecture', $prefecture);
        }

        if ($from = ($filters['from'] ?? null)) {
            $query->where('event_date', '>=', Carbon::parse($from));
        }

        if ($to = ($filters['to'] ?? null)) {
            $toDate = Carbon::parse($to);
            if ($toDate->hour === 0 && $toDate->minute === 0 && $toDate->second === 0) {
                $toDate = $toDate->endOfDay();
            }
            $query->where('event_date', '<=', $toDate);
        }

        return $query->orderBy('event_date', 'asc');
    }
}
