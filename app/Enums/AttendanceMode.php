<?php

namespace App\Enums;

/** 参加形態（オンライン／対面） */
enum AttendanceMode: string
{
    case Online = 'online';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'オンライン',
            self::InPerson => '対面',
        };
    }
}
