<?php

namespace App\Enum;

enum VerificationTypeEnum: string
{
    case PHONE = 'phone';
    case EMAIL = 'email';

    public static function isValid(mixed $type): bool
    {
        return in_array($type, [self::PHONE->value, self::EMAIL->value]);
    }
}
