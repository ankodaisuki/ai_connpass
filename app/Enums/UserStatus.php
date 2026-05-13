<?php

namespace App\Enums;

/**
 * ユーザーステータス
 */
enum UserStatus: int
{
    case Inactive = 0;
    case Active = 1;
}
