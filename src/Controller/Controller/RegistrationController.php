<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\DataTransferObject\RegistrationFormDto;
use App\Entity\User;
use App\Enum\VerificationTypeEnum;
use App\Form\Type\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\LoginCodeAuthenticator;
use App\Service\Auth\LoginCodeService;
use App\Service\Billing\ReferralAttribution;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        LoginCodeService $loginCodeService,
        ReferralAttribution $referralAttribution,
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.login_code_request')]
        RateLimiterFactoryInterface $loginCodeRequestLimiter,
    ): Response {
        // FULLY, not merely "has a user", for the same reason as the login
        // page: a remember-me cookie makes getUser() non-null while granting
        // only IS_AUTHENTICATED_REMEMBERED, which reaches no page in this app.
        // Turning that visitor away from registration left them able to neither
        // register nor get in.
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        $dto = new RegistrationFormDto();
        $form = $this->createForm(RegistrationFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $dto->email));

            if (! $loginCodeRequestLimiter->create($email)->consume()->isAccepted()) {
                $form->addError(new FormError($translator->trans('login.error.too_many_requests')));

                return $this->render('security/register.html.twig', [
                    'form' => $form,
                    'referredBy' => $referralAttribution->referrerFor(
                        $referralAttribution->pending($request->getSession()),
                    ),
                ]);
            }

            // An address that already exists is not told so — it simply gets a
            // code, which lands it in the same place a sign-in would.
            if ($userRepository->findOneBy([
                'email' => $email,
            ]) === null) {
                $user = new User($email);

                // Off the session and onto the row, because the next step is an
                // emailed code that is as likely to be opened on a phone. Only
                // for a genuinely new account: an existing user arriving on
                // somebody's link is not a referral, and their practice already
                // exists.
                $user->setReferredByCode($referralAttribution->pending($request->getSession()));

                $entityManager->persist($user);
                $entityManager->flush();
            }

            $loginCodeService->issue(VerificationTypeEnum::EMAIL, $email);
            $request->getSession()->set(LoginCodeAuthenticator::SESSION_DESTINATION, $email);

            return $this->redirectToRoute('app_login_verify');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
            'referredBy' => $referralAttribution->referrerFor(
                $referralAttribution->pending($request->getSession()),
            ),
        ]);
    }
}
