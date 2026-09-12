<?php

declare(strict_types=1);

namespace App\Message\Message;

/**
 * "Send this month's digest to this subscriber." One message per address, so
 * a bounce or a bad template fails one recipient rather than the whole run,
 * and the failed transport says exactly who was missed.
 */
final readonly class SendNewsletterMessage
{
    public function __construct(
        public string $subscriberId,
        /** The window the digest covers, as Y-m-d, so a retried message sends
         *  the same digest it was queued for rather than a shifted one. */
        public string $from,
        public string $to,
    ) {
    }
}
