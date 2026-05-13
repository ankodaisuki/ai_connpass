<?php

namespace App\Enums;

/**
 * イベントステータス
 */
enum EventStatus: int
{
    case Draft = 0;
    case Published = 1;
    case Private = 2;
}
