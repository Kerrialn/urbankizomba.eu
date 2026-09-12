<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

/**
 * Signing in, end to end, through the real authenticator. Nothing else in the
 * suite proves a visitor can get in, and the firewall can be changed with the
 * whole suite staying green otherwise.
 */
final class SignInTest extends WebTestCaseWithTransaction
{
    public function testACodeFromTheEmailSignsTheUserIn(): void
    {
        $email = $this->uniqueEmail('signin');
        $this->user($email);

        $this->signIn($email);

        // The real proof: a page that demands IS_AUTHENTICATED_FULLY opens.
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
    }

    public function testRegistrationCreatesAnAccountAndSignsIn(): void
    {
        $email = $this->uniqueEmail('register');

        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form')->form([
            'registration_form[email]' => $email,
        ]));
        self::assertResponseRedirects('/login/verify');

        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form')->form();
        $form['login_code_form[code]'] = $this->issuedCodeFor($email);
        $this->client->submit($form);

        self::assertResponseRedirects('/account');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testAnUnknownAddressGetsNoCodeButTheSameResponse(): void
    {
        $email = $this->uniqueEmail('unknown');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form([
            'login_request_form[email]' => $email,
        ]));

        // Same redirect as a known address: whether an address has an account
        // is not something the login form should reveal.
        self::assertResponseRedirects('/login/verify');
        self::assertNull($this->queuedTemplatedMail($email, 'email/login_code.html.twig'));
    }

    public function testAWrongCodeIsRefused(): void
    {
        $email = $this->uniqueEmail('wrong');
        $this->user($email);

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form([
            'login_request_form[email]' => $email,
        ]));
        $crawler = $this->client->followRedirect();

        $form = $crawler->filter('form')->form();
        $form['login_code_form[code]'] = '000000';
        $this->client->submit($form);

        self::assertResponseRedirects('/login/verify');
        $this->client->followRedirect();

        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login');
    }

    public function testTheLoginEmailRenders(): void
    {
        $email = $this->uniqueEmail('render');
        $this->user($email);

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form([
            'login_request_form[email]' => $email,
        ]));

        $mail = $this->queuedTemplatedMail($email, 'email/login_code.html.twig');
        self::assertInstanceOf(TemplatedEmail::class, $mail);

        $body = self::getContainer()->get('twig')->render((string) $mail->getHtmlTemplate(), $mail->getContext());

        self::assertStringContainsString((string) $mail->getContext()['code'], $body);
        self::assertStringNotContainsString('email.login_code.', $body, 'An untranslated key reached the email.');
    }

    private function signIn(string $email): void
    {
        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form')->form([
            'login_request_form[email]' => $email,
        ]));

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $form = $crawler->filter('form')->form();
        $form['login_code_form[code]'] = $this->issuedCodeFor($email);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /**
     * Only the hash of the code is stored, so the test reads the code out of
     * the queued email's context.
     */
    private function issuedCodeFor(string $email): string
    {
        $mail = $this->queuedTemplatedMail($email, 'email/login_code.html.twig');
        self::assertInstanceOf(TemplatedEmail::class, $mail, 'No sign-in email was queued.');

        $code = (string) ($mail->getContext()['code'] ?? '');
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);

        return $code;
    }

    /**
     * SendEmailMessage is routed async, so the mail is on the transport rather
     * than in the mailer's collected messages.
     */
    private function queuedTemplatedMail(string $email, string $template): ?TemplatedEmail
    {
        $found = null;

        foreach (self::getContainer()->get('messenger.transport.async')->get() as $envelope) {
            $message = $envelope->getMessage();

            if (! $message instanceof SendEmailMessage) {
                continue;
            }

            $mail = $message->getMessage();

            if (! $mail instanceof TemplatedEmail || $mail->getHtmlTemplate() !== $template) {
                continue;
            }

            if ($mail->getTo() !== [] && $mail->getTo()[0]->getAddress() === $email) {
                $found = $mail;
            }
        }

        return $found;
    }
}
