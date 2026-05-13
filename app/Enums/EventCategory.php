<?php

namespace App\Enums;

/**
 * イベントカテゴリー
 */
enum EventCategory: int
{
    case Frontend = 1;
    case Backend = 2;
    case Database = 3;
    case Mobile = 4;
    case Ai = 5;
    case Other = 6;
}
