<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\VerificationTypeEnum;
use App\Service\Auth\LoginCodeService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Verifies the one-time code the user typed, then logs them in.
 *
 * The address being verified lives in the session, not the form: it is chosen in
 * the first step and must not be swappable when the code is submitted.
 */
final class LoginCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const string LOGIN_ROUTE = 'app_login';

    public const string VERIFY_ROUTE = 'app_login_verify';

    public const string SESSION_DESTINATION = '_login_code_destination';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoginCodeService $loginCodeService,
    ) {
    }

    public function supports(Request $request): bool
    {
        // Routes are localized, so "_route" is app_login_verify.cs or .en and
        // never the bare name. "_canonical_route" is the name without the
        // language suffix — comparing against "_route" would silently stop
        // matching, and the form would post into a 405 instead of logging in.
        $route = $request->attributes->get('_canonical_route')
            ?? $request->attributes->get('_route');

        return $request->isMethod('POST') && $route === self::VERIFY_ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $destination = $request->getSession()->get(self::SESSION_DESTINATION);

        if (! is_string($destination) || $destination === '') {
            throw new CustomUserMessageAuthenticationException('login.error.session_expired');
        }

        /** @var array{code?: string, _token?: string} $submitted */
        $submitted = $request->request->all('login_code_form');

        $verified = $this->loginCodeService->verify(
            VerificationTypeEnum::EMAIL,
            $destination,
            (string) ($submitted['code'] ?? ''),
        );

        if ($verified === null) {
            throw new CustomUserMessageAuthenticationException('login.error.invalid_code');
        }

        return new SelfValidatingPassport(
            new UserBadge($verified),
            [
                new CsrfTokenBadge('authenticate', (string) ($submitted['_token'] ?? '')),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->getSession()->remove(self::SESSION_DESTINATION);

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        // The dashboard, not the marketing page: someone who has just signed in
        // came to do something, and app_home has nothing on it for them.
        return new RedirectResponse($targetPath ?? $this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate(self::VERIFY_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
