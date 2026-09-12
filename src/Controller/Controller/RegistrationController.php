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
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.login_code_request')]
        RateLimiterFactoryInterface $loginCodeRequestLimiter,
    ): Response {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_account');
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
                ]);
            }

            // An address that already exists is not told so — it simply gets a
            // code, which lands it in the same place a sign-in would.
            if (! $userRepository->findOneByEmail($email) instanceof \App\Entity\User) {
                $entityManager->persist(new User($email));
                $entityManager->flush();
            }

            $loginCodeService->issue(VerificationTypeEnum::EMAIL, $email);
            $request->getSession()->set(LoginCodeAuthenticator::SESSION_DESTINATION, $email);

            return $this->redirectToRoute('app_login_verify');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }
}
