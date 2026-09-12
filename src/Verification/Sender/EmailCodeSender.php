<?php

declare(strict_types=1);

namespace App\Verification\Sender;

use App\Enum\VerificationTypeEnum;
use App\Verification\Contract\VerificationSenderInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EmailCodeSender implements VerificationSenderInterface
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

    public function supports(VerificationTypeEnum $type): bool
    {
        return $type === VerificationTypeEnum::EMAIL;
    }

    /**
     * $message is the bare code. The subject carries it too: on a phone the
     * notification preview is often all anyone reads before typing it in.
     */
    public function send(string $destination, string $message): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->site['email'], $this->site['name']))
            ->to($destination)
            ->subject($this->translator->trans('email.login_code.subject', [
                'code' => $message,
            ]))
            ->htmlTemplate('email/login_code.html.twig')
            ->context([
                'code' => $message,
                'ttl_minutes' => 10,
                // Captured in the request, because the body is rendered later
                // by the Messenger worker at the default locale.
                'locale' => $this->localeSwitcher->getLocale(),
            ]);

        $this->mailer->send($email);
    }
}
