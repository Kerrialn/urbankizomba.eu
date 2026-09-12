<?php

declare(strict_types=1);

namespace App\Controller\Newsletter;

use App\DataTransferObject\NewsletterSignupDto;
use App\Entity\Subscriber;
use App\Form\Type\NewsletterSignupFormType;
use App\Repository\SubscriberRepository;
use App\Service\Newsletter\ConfirmationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Double opt-in newsletter. Nothing is sent to an address until the person
 * holding it has clicked the link we mailed them.
 */
final class NewsletterController extends AbstractController
{
    #[Route('/newsletter', name: 'app_newsletter', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('newsletter/index.html.twig', [
            'form' => $this->createForm(NewsletterSignupFormType::class, new NewsletterSignupDto(), [
                'action' => $this->generateUrl('app_newsletter_signup'),
            ]),
        ]);
    }

    #[Route('/newsletter/signup', name: 'app_newsletter_signup', methods: ['POST'])]
    public function signup(
        Request $request,
        SubscriberRepository $subscriberRepository,
        EntityManagerInterface $entityManager,
        ConfirmationMailer $confirmationMailer,
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.newsletter_signup')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        $dto = new NewsletterSignupDto();
        $form = $this->createForm(NewsletterSignupFormType::class, $dto, [
            'action' => $this->generateUrl('app_newsletter_signup'),
        ]);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->render('newsletter/index.html.twig', [
                'form' => $form,
            ]);
        }

        $email = mb_strtolower(trim((string) $dto->email));

        if (! $limiter->create($request->getClientIp() ?? $email)->consume()->isAccepted()) {
            $this->addFlash('error', $translator->trans('newsletter.flash.too_many'));

            return $this->redirectToRoute('app_newsletter');
        }

        $subscriber = $subscriberRepository->findOneByEmail($email);

        if (! $subscriber instanceof Subscriber) {
            $subscriber = new Subscriber($email);
            $entityManager->persist($subscriber);
            $entityManager->flush();
        }

        // The same message whether the address is new, pending or already
        // confirmed: who is subscribed is not public. An already confirmed
        // address simply gets a mail saying so.
        $confirmationMailer->send($subscriber);

        $this->addFlash('success', $translator->trans('newsletter.flash.check_inbox'));

        return $this->redirectToRoute('app_newsletter');
    }

    #[Route('/newsletter/confirm/{token}', name: 'app_newsletter_confirm', requirements: [
        'token' => '[a-f0-9]{48}',
    ], methods: ['GET'])]
    public function confirm(string $token, SubscriberRepository $subscriberRepository, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $subscriber = $subscriberRepository->findOneByToken($token);

        if (! $subscriber instanceof Subscriber) {
            $this->addFlash('error', $translator->trans('newsletter.flash.bad_link'));

            return $this->redirectToRoute('app_newsletter');
        }

        $subscriber->confirm();
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('newsletter.flash.confirmed'));

        return $this->redirectToRoute('app_events');
    }

    /**
     * A GET, deliberately: the link is in an email, and a one-click unsubscribe
     * is what mail clients and the law both expect. The token is the only
     * authentication, which is fine for a right anyone should have anyway.
     */
    #[Route('/newsletter/unsubscribe/{token}', name: 'app_newsletter_unsubscribe', requirements: [
        'token' => '[a-f0-9]{48}',
    ], methods: ['GET'])]
    public function unsubscribe(string $token, SubscriberRepository $subscriberRepository, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $subscriber = $subscriberRepository->findOneByToken($token);

        if ($subscriber instanceof Subscriber) {
            $subscriber->unsubscribe();
            $entityManager->flush();
        }

        $this->addFlash('success', $translator->trans('newsletter.flash.unsubscribed'));

        return $this->redirectToRoute('app_home');
    }
}
