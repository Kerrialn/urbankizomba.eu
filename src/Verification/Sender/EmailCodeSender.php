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
     * @param array<string, string> $company
     */
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LocaleSwitcher $localeSwitcher,
        #[Autowire('%app.company%')]
        private array $company,
    ) {
    }

    public function supports(VerificationTypeEnum $type): bool
    {
        return $type === VerificationTypeEnum::EMAIL;
    }

    /**
     * $message is the bare code. The subject carries it too: on a phone the
     * notification preview is often all the doctor needs to read before typing
     * it into the desktop browser.
     */
    public function send(string $destination, string $message): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->company['email'], $this->company['trading_name']))
            ->to($destination)
            ->subject($this->translator->trans('email.login_code.subject', [
                'code' => $message,
            ]))
            ->htmlTemplate('email/login_code.html.twig')
            ->context([
                'code' => $message,
                'ttl_minutes' => 10,
                // Captured here, in the request, because the body is rendered
                // later by the Messenger worker: SendEmailMessage is routed
                // async, and the worker translates at the default locale. A user
                // reading the site in English would otherwise get a Czech email.
                'locale' => $this->localeSwitcher->getLocale(),
            ]);

        $this->mailer->send($email);
    }
}
