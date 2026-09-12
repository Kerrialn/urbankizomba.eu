<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The channels a one-time code can be sent over. Email is the only one today;
 * the enum exists so that adding another is a new case and a new sender, not a
 * change to the login service.
 */
enum VerificationTypeEnum: string
{
    case EMAIL = 'email';
}
