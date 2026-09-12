<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\LoginCodeRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Expired codes are useless but still identify who tried to sign in and when.
 * Keeping them is a data-protection liability, not a feature — so drop them.
 */
#[AsCommand(
    name: 'app:purge-login-codes',
    description: 'Delete login codes that expired more than a day ago.',
)]
final class PurgeLoginCodesCommand extends Command
{
    public function __construct(
        private readonly LoginCodeRepository $loginCodeRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->loginCodeRepository->purgeExpired(new DateTimeImmutable('-1 day'));

        (new SymfonyStyle($input, $output))->success(sprintf('Purged %d expired login code(s).', $deleted));

        return Command::SUCCESS;
    }
}
