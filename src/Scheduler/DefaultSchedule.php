<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The single `scheduler_default` schedule, assembled from every
 * {@see ScheduledTaskInterface} in src/Scheduler/Task.
 *
 * Consumed by `messenger:consume scheduler_default` (see supervisord.conf).
 */
#[AsSchedule('default')]
final readonly class DefaultSchedule implements ScheduleProviderInterface
{
    /**
     * @param iterable<ScheduledTaskInterface> $tasks
     */
    public function __construct(
        private CacheInterface $cache,
        #[AutowireIterator('app.scheduled_task')]
        private iterable $tasks,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true); // ensure only last missed task is run

        foreach ($this->tasks as $task) {
            $schedule->add($task->getRecurringMessage());
        }

        return $schedule;
    }
}
