<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use App\Validator\TurnstileValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TurnstileConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(CLOUDFLARE_TURNSTILE_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment = 'prod',
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof TurnstileValidator) {
            throw new UnexpectedTypeException($constraint, TurnstileValidator::class);
        }

        // A functional test cannot produce a Turnstile token, so without this no
        // form carrying the widget — registration included — can be submitted in
        // a test at all. Restricted to the test environment on purpose: dev still
        // exercises the real challenge, so a broken key is caught before prod.
        if ($this->environment === 'test') {
            return;
        }

        if ($value === null || $value === '') {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();

            return;
        }

        $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret' => $this->secretKey,
                'response' => $value,
            ],
        ]);

        $data = $response->toArray(false);

        if (! ($data['success'] ?? false)) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
