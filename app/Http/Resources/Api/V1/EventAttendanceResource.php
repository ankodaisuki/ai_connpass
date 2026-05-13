<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 参加者一覧用のリソース
 *
 * @mixin EventAttendance
 */
class EventAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'applied_at' => $this->applied_at->toIso8601ZuluString(),
        ];
    }
}
