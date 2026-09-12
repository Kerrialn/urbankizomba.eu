<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Makes an address an admin, creating the account if it does not exist yet.
 * There is no other way to get the first admin: the role is never granted
 * through the site.
 */
#[AsCommand(
    name: 'app:user:promote',
    description: 'Grant ROLE_ADMIN to an email address.',
)]
final class PromoteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The address to promote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $io->error(sprintf('"%s" is not an email address.', $email));

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneByEmail($email);

        if (! $user instanceof User) {
            $user = new User($email);
            $this->entityManager->persist($user);
        }

        $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])));
        $this->entityManager->flush();

        $io->success(sprintf('%s is now an admin. They sign in with a code like anyone else.', $email));

        return Command::SUCCESS;
    }
}
