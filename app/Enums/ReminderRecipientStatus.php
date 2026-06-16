<?php

namespace App\Enums;

/**
 * リマインド受信者配信ステータス
 */
enum ReminderRecipientStatus: int
{
    case Pending = 0;
    case Sent = 1;
    case Failed = 2;
}
