<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 自分の申し込み一覧用のリソース（event情報を含む）
 *
 * @mixin EventAttendance
 */
class MyAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'event_date' => $this->event->event_date->toIso8601ZuluString(),
                'prefecture' => $this->event->prefecture,
                'location' => $this->event->location,
                'capacity' => $this->event->capacity,
            ],
            'applied_at' => $this->applied_at->toIso8601ZuluString(),
        ];
    }
}
