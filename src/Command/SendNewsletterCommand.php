<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\Message\SendNewsletterMessage;
use App\Repository\EventRepository;
use App\Repository\SubscriberRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the monthly digest: every dated event starting in the next two
 * months, to every confirmed subscriber.
 *
 * Run by hand, on purpose. A newsletter that goes out on a timer goes out with
 * whatever is on the calendar that morning, and the month it is worth reading
 * is the one somebody looked at the list first. Refuses to send to anyone who
 * received one in the last twenty days, so running it twice is harmless.
 */
#[AsCommand(
    name: 'app:newsletter:send',
    description: 'Queue the events digest for every confirmed subscriber.',
)]
final class SendNewsletterCommand extends Command
{
    private const int WINDOW_DAYS = 62;

    private const int MIN_DAYS_BETWEEN = 20;

    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly EventRepository $eventRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be sent without queueing anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $from = new DateTimeImmutable('today');
        $to = $from->modify(sprintf('+%d days', self::WINDOW_DAYS));
        $events = $this->eventRepository->findStartingBetween($from, $to);

        if ($events === []) {
            $io->warning('No approved events start in the next two months. Nothing sent.');

            return Command::SUCCESS;
        }

        $cutoff = new DateTimeImmutable(sprintf('-%d days', self::MIN_DAYS_BETWEEN));
        $queued = 0;
        $skipped = 0;

        foreach ($this->subscriberRepository->findConfirmed() as $subscriber) {
            $lastSent = $subscriber->getLastSentAt();

            if ($lastSent instanceof DateTimeImmutable && $lastSent > $cutoff) {
                ++$skipped;
                continue;
            }

            if (! $dryRun) {
                $this->messageBus->dispatch(new SendNewsletterMessage(
                    $subscriber->getId()->toRfc4122(),
                    $from->format('Y-m-d'),
                    $to->format('Y-m-d'),
                ));
            }

            ++$queued;
        }

        $io->success(sprintf(
            '%s %d digest(s) covering %d event(s); %d subscriber(s) skipped as recently sent.',
            $dryRun ? 'Would queue' : 'Queued',
            $queued,
            count($events),
            $skipped,
        ));

        return Command::SUCCESS;
    }
}
