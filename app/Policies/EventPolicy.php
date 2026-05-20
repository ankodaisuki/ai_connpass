<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * イベントに対するアクセス制御
 *
 * 閲覧系 (view/viewAny) はコントローラ内で判定するため Policy には含めない。
 */
class EventPolicy
{
    /**
     * 更新は作成者本人のみ許可
     */
    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    /**
     * 削除は作成者本人のみ許可
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    /**
     * 出欠記録は作成者本人のみ許可
     */
    public function updateAttendance(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
