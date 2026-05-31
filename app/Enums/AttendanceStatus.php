<?php

namespace App\Enums;

/**
 * イベント申し込みステータス
 */
enum AttendanceStatus: int
{
    case Applied = 0;
    case Cancelled = 1;
    case Waitlisted = 2;
}
