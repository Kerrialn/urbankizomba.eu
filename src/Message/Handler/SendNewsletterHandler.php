<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Entity\Subscriber;
use App\Message\Message\SendNewsletterMessage;
use App\Repository\EventRepository;
use App\Repository\SubscriberRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendNewsletterHandler
{
    /**
     * @param array<string, string> $site
     */
    public function __construct(
        private SubscriberRepository $subscriberRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire('%app.site%')]
        private array $site,
    ) {
    }

    public function __invoke(SendNewsletterMessage $message): void
    {
        $subscriber = $this->subscriberRepository->find($message->subscriberId);

        // Unsubscribed between queueing and sending: the message is simply
        // dropped. Silence is the right answer to an unsubscribe.
        if (! $subscriber instanceof Subscriber || ! $subscriber->isConfirmed()) {
            return;
        }

        $from = new DateTimeImmutable($message->from);
        $to = new DateTimeImmutable($message->to);
        $events = $this->eventRepository->findStartingBetween($from, $to);

        $email = (new TemplatedEmail())
            ->from(new Address($this->site['email'], $this->site['name']))
            ->to($subscriber->getEmail())
            ->subject($this->translator->trans('email.newsletter.subject', [
                'month' => $from->format('F Y'),
            ]))
            ->htmlTemplate('email/newsletter.html.twig')
            ->context([
                'subscriber' => $subscriber,
                'events' => $events,
                'from' => $from,
                'to' => $to,
                'locale' => 'en',
            ]);

        $this->mailer->send($email);

        $subscriber->markSent();
        $this->entityManager->flush();
    }
}
