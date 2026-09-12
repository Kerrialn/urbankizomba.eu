<?php

declare(strict_types=1);

namespace App\Validator;

use App\Validator\Constraint\TurnstileConstraintValidator;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class TurnstileValidator extends Constraint
{
    public string $message = 'turnstile.verification_failed';

    public function __construct(
        ?string $message = null,
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options ?? [], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function validatedBy(): string
    {
        return TurnstileConstraintValidator::class;
    }
}
