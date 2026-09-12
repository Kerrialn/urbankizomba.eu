<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Subscriber;
use App\Message\Message\SendNewsletterMessage;
use App\Repository\SubscriberRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

/**
 * Double opt-in, one-click out, and a digest that renders.
 */
final class NewsletterTest extends WebTestCaseWithTransaction
{
    public function testSignupSendsAConfirmationAndNothingElseUntilConfirmed(): void
    {
        $email = $this->uniqueEmail('news');

        $crawler = $this->client->request('GET', '/newsletter');
        $this->client->submit($crawler->filter('form')->form([
            'newsletter_signup_form[email]' => $email,
        ]));
        self::assertResponseRedirects('/newsletter');

        $subscriber = self::getContainer()->get(SubscriberRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(Subscriber::class, $subscriber);
        self::assertFalse($subscriber->isConfirmed());

        $mail = $this->queuedMail($email, 'email/newsletter_confirm.html.twig');
        self::assertInstanceOf(TemplatedEmail::class, $mail);

        $body = self::getContainer()->get('twig')->render((string) $mail->getHtmlTemplate(), $mail->getContext());
        self::assertStringContainsString('/newsletter/confirm/' . $subscriber->getToken(), $body);
        self::assertStringNotContainsString('email.newsletter_confirm.', $body);

        // The link is what confirms.
        $this->client->request('GET', '/newsletter/confirm/' . $subscriber->getToken());
        self::assertResponseRedirects('/events');

        $subscriber = $this->em->find(Subscriber::class, $subscriber->getId());
        self::assertInstanceOf(Subscriber::class, $subscriber);
        self::assertTrue($subscriber->isConfirmed());

        // And the other link is what unsubscribes.
        $this->client->request('GET', '/newsletter/unsubscribe/' . $subscriber->getToken());
        self::assertResponseRedirects('/');

        $subscriber = $this->em->find(Subscriber::class, $subscriber->getId());
        self::assertInstanceOf(Subscriber::class, $subscriber);
        self::assertFalse($subscriber->isConfirmed());
    }

    public function testABadTokenIsNotAConfirmation(): void
    {
        $this->client->request('GET', '/newsletter/confirm/' . str_repeat('0', 48));
        self::assertResponseRedirects('/newsletter');
    }

    public function testTheDigestGoesToConfirmedSubscribersOnlyAndRenders(): void
    {
        $confirmed = new Subscriber($this->uniqueEmail('confirmed'));
        $confirmed->confirm();
        $pending = new Subscriber($this->uniqueEmail('pending'));
        $this->em->persist($confirmed);
        $this->em->persist($pending);
        $this->em->flush();

        $this->event('Digest Festival', startsAt: new \DateTimeImmutable('+20 days'));

        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $tester = new CommandTester($application->find('app:newsletter:send'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $recipients = [];

        foreach (self::getContainer()->get('messenger.transport.async')->get() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof SendNewsletterMessage) {
                $recipients[] = $message->subscriberId;
            }
        }

        self::assertSame([$confirmed->getId()->toRfc4122()], $recipients);

        // Run the handler directly: it renders the digest and sends it.
        $message = new SendNewsletterMessage(
            $confirmed->getId()->toRfc4122(),
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            (new \DateTimeImmutable('+62 days'))->format('Y-m-d'),
        );
        self::getContainer()->get(\App\Message\Handler\SendNewsletterHandler::class)($message);

        $mail = $this->queuedMail($confirmed->getEmail(), 'email/newsletter.html.twig');
        self::assertInstanceOf(TemplatedEmail::class, $mail);

        $body = self::getContainer()->get('twig')->render((string) $mail->getHtmlTemplate(), $mail->getContext());
        self::assertStringContainsString('Digest Festival', $body);
        self::assertStringContainsString('/newsletter/unsubscribe/' . $confirmed->getToken(), $body);
        self::assertStringNotContainsString('email.newsletter.', $body);

        $confirmed = $this->em->find(Subscriber::class, $confirmed->getId());
        self::assertInstanceOf(Subscriber::class, $confirmed);
        self::assertNotNull($confirmed->getLastSentAt());
    }

    private function queuedMail(string $email, string $template): ?TemplatedEmail
    {
        $found = null;

        foreach (self::getContainer()->get('messenger.transport.async')->get() as $envelope) {
            $message = $envelope->getMessage();

            if (! $message instanceof SendEmailMessage) {
                continue;
            }

            $mail = $message->getMessage();

            if ($mail instanceof TemplatedEmail && $mail->getHtmlTemplate() === $template && $mail->getTo()[0]->getAddress() === $email) {
                $found = $mail;
            }
        }

        return $found;
    }
}
