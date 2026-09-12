<?php

declare(strict_types=1);

namespace App\Verification\Sender;

use App\Enum\VerificationTypeEnum;
use App\Verification\Contract\VerificationSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class VerificationSenderResolver
{
    /**
     * @param iterable<VerificationSenderInterface> $senders
     */
    public function __construct(
        #[AutowireIterator('app.verification_sender')]
        private iterable $senders
    ) {}

    public function for(VerificationTypeEnum $type): VerificationSenderInterface
    {
        foreach ($this->senders as $s) {
            if ($s->supports($type)) {
                return $s;
            }
        }
        throw new \LogicException("No sender supports {$type->value}");
    }
}
