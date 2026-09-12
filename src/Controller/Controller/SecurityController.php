<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\DataTransferObject\LoginCodeDto;
use App\DataTransferObject\LoginRequestDto;
use App\Enum\VerificationTypeEnum;
use App\Form\Type\LoginCodeFormType;
use App\Form\Type\LoginRequestFormType;
use App\Repository\UserRepository;
use App\Security\LoginCodeAuthenticator;
use App\Service\Auth\LoginCodeService;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    /**
     * Step one: collect the address and send a code to it.
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        LoginCodeService $loginCodeService,
        UserRepository $userRepository,
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.login_code_request')]
        RateLimiterFactoryInterface $loginCodeRequestLimiter,
    ): Response {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_account');
        }

        $dto = new LoginRequestDto();
        $form = $this->createForm(LoginRequestFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $dto->email));

            if (! $loginCodeRequestLimiter->create($email)->consume()->isAccepted()) {
                $form->addError(new FormError($translator->trans('login.error.too_many_requests')));

                return $this->render('security/login.html.twig', [
                    'form' => $form,
                ]);
            }

            // Only issue a code to an address we know. The response is identical
            // either way: whether an address has an account is not public.
            if ($userRepository->findOneByEmail($email) instanceof \App\Entity\User) {
                $loginCodeService->issue(VerificationTypeEnum::EMAIL, $email);
            }

            $request->getSession()->set(LoginCodeAuthenticator::SESSION_DESTINATION, $email);

            return $this->redirectToRoute('app_login_verify');
        }

        return $this->render('security/login.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Step two: the code itself. The POST is intercepted by
     * {@see LoginCodeAuthenticator}, so this action only ever renders.
     */
    #[Route('/login/verify', name: 'app_login_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_account');
        }

        $destination = $request->getSession()->get(LoginCodeAuthenticator::SESSION_DESTINATION);

        if (! is_string($destination) || $destination === '') {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/verify.html.twig', [
            'form' => $this->createForm(LoginCodeFormType::class, new LoginCodeDto()),
            'destination' => $destination,
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Intercepted by the firewall's logout listener — never actually executed.
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new LogicException('Intercepted by the logout key on the firewall.');
    }
}
