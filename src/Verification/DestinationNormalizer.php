<?php

declare(strict_types=1);

namespace App\Verification;

use App\Enum\VerificationTypeEnum;

/**
 * One canonical form per destination, so the address a code was issued to and
 * the address it is verified against compare equal however they were typed.
 */
final readonly class DestinationNormalizer
{
    public function normalize(VerificationTypeEnum $type, string $raw): string
    {
        return match ($type) {
            VerificationTypeEnum::EMAIL => mb_strtolower(trim($raw)),
        };
    }
}
