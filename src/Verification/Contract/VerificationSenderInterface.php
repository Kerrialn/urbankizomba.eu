<?php

namespace App\Verification\Contract;

use App\Enum\VerificationTypeEnum;

interface VerificationSenderInterface
{
    public function supports(VerificationTypeEnum $type): bool;

    public function send(string $destination, string $message): void;
}