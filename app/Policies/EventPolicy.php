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
     * 更新はオーナーまたは承諾済み合同主催者に許可
     */
    public function update(User $user, Event $event): bool
    {
        return $event->isOrganizer($user);
    }

    /**
     * 削除はオーナーのみ許可
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * 出欠記録はオーナーまたは承諾済み合同主催者に許可
     */
    public function updateAttendance(User $user, Event $event): bool
    {
        return $event->isOrganizer($user);
    }

    /**
     * 合同主催者の招待はオーナーのみ許可
     */
    public function inviteOrganizer(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * 合同主催者の除名はオーナーのみ許可
     */
    public function removeOrganizer(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * オーナーの移譲はオーナーのみ許可
     */
    public function transferOwnership(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }
}
