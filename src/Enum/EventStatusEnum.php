<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a submission is in review. Only APPROVED reaches a public page.
 */
enum EventStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return 'event.status.' . $this->value;
    }
}
