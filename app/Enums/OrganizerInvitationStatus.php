<?php

namespace App\Enums;

/**
 * 合同主催者の招待状態
 */
enum OrganizerInvitationStatus: int
{
    case Pending = 0;
    case Accepted = 1;
    case Declined = 2;
}
