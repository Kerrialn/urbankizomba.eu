<?php

declare(strict_types=1);

namespace App\Verification;

use App\Enum\VerificationTypeEnum;
use InvalidArgumentException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final readonly class DestinationNormalizer
{
    public function __construct(
        private ?PhoneNumberUtil $phoneNumberUtil = null
    ) {

    }

    public function normalize(VerificationTypeEnum $type, string $raw, string $defaultRegion = 'CZ'): string
    {
        return match ($type) {
            VerificationTypeEnum::PHONE => $this->toE164($raw, $defaultRegion),
            VerificationTypeEnum::EMAIL => mb_strtolower(trim($raw)),
        };
    }

    private function toE164(string $raw, string $region): string
    {
        $util = $this->phoneNumberUtil ?? PhoneNumberUtil::getInstance();
        $n = $util->parse($raw, $region);
        if (! $util->isValidNumber($n)) {
            throw new InvalidArgumentException('Invalid phone number');
        }
        return $util->format($n, PhoneNumberFormat::E164);
    }
}
