<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * A single recurring task. One task per class — implementations are collected
 * automatically by {@see DefaultSchedule}, so adding a task needs no wiring.
 */
#[AutoconfigureTag('app.scheduled_task')]
interface ScheduledTaskInterface
{
    public function getRecurringMessage(): RecurringMessage;
}
