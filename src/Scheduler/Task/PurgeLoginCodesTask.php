<?php

declare(strict_types=1);

namespace App\Scheduler\Task;

use App\Scheduler\ScheduledTaskInterface;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Daily at 03:00: housekeeping nobody waits on.
 */
final readonly class PurgeLoginCodesTask implements ScheduledTaskInterface
{
    public function getRecurringMessage(): RecurringMessage
    {
        return RecurringMessage::every('1 day', new RunCommandMessage('app:purge-login-codes'), from: '03:00');
    }
}
