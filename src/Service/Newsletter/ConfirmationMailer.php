<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use App\Entity\Subscriber;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ConfirmationMailer
{
    /**
     * @param array<string, string> $site
     */
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LocaleSwitcher $localeSwitcher,
        #[Autowire('%app.site%')]
        private array $site,
    ) {
    }

    public function send(Subscriber $subscriber): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->site['email'], $this->site['name']))
            ->to($subscriber->getEmail())
            ->subject($this->translator->trans('email.newsletter_confirm.subject'))
            ->htmlTemplate('email/newsletter_confirm.html.twig')
            ->context([
                'subscriber' => $subscriber,
                'already_confirmed' => $subscriber->isConfirmed(),
                'locale' => $this->localeSwitcher->getLocale(),
            ]);

        $this->mailer->send($email);
    }
}
