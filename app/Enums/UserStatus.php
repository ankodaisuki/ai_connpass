<?php

namespace App\Enums;

/**
 * ユーザーステータス
 */
enum UserStatus: int
{
    case Inactive = 0;
    case Active = 1;
    case Frozen = 2;
}
