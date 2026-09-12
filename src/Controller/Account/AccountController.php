<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\DataTransferObject\EventSubmissionDto;
use App\Entity\Event;
use App\Entity\User;
use App\Form\Type\EventSubmissionFormType;
use App\Repository\EventRepository;
use App\Service\Event\EventFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Everything a signed-in visitor can do: submit an event, see what they have
 * submitted and where it is in review, edit it, and confirm a social is still
 * running.
 *
 * Signed-in only, and not because any of it is sensitive: a submission that
 * cannot be replied to is one nobody can ask a question about, and an open
 * form on a site this size is a spam inbox.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function index(
        EventRepository $eventRepository,
        #[Autowire('%app.social_confirmation_days%')]
        int $confirmationDays,
    ): Response {
        return $this->render('account/index.html.twig', [
            'events' => $eventRepository->findSubmittedBy($this->user()),
            'confirmation_days' => $confirmationDays,
        ]);
    }

    #[Route('/submit', name: 'app_submit', methods: ['GET', 'POST'])]
    public function submit(Request $request, EventFactory $eventFactory, TranslatorInterface $translator): Response
    {
        $dto = new EventSubmissionDto();
        $form = $this->createForm(EventSubmissionFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $eventFactory->create($dto, $this->user());

            $this->addFlash('success', $translator->trans('submit.flash.received'));

            return $this->redirectToRoute('app_event', [
                'slug' => $event->getSlug(),
            ]);
        }

        return $this->render('account/submit.html.twig', [
            'form' => $form,
            'event' => null,
        ]);
    }

    #[Route('/account/events/{id}/edit', name: 'app_account_event_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, EventRepository $eventRepository, EventFactory $eventFactory, TranslatorInterface $translator): Response
    {
        $event = $this->ownEvent($id, $eventRepository);

        $dto = EventSubmissionDto::fromEvent($event);
        $form = $this->createForm(EventSubmissionFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventFactory->update($event, $dto);

            $this->addFlash('success', $translator->trans('submit.flash.updated'));

            return $this->redirectToRoute('app_event', [
                'slug' => $event->getSlug(),
            ]);
        }

        return $this->render('account/submit.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    /**
     * "Yes, this social is still on." A POST because it changes a date, and a
     * button on the account page because that is where the reminder lives.
     */
    #[Route('/account/events/{id}/confirm', name: 'app_account_event_confirm', methods: ['POST'])]
    public function confirm(string $id, Request $request, EventRepository $eventRepository, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $event = $this->ownEvent($id, $eventRepository);

        if (! $this->isCsrfTokenValid('confirm-' . $event->getId()->toRfc4122(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($event->isRecurring()) {
            $event->confirm();
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('account.flash.confirmed'));
        }

        return $this->redirectToRoute('app_account');
    }

    private function ownEvent(string $id, EventRepository $eventRepository): Event
    {
        $event = $eventRepository->find($id);

        // Not found rather than forbidden for somebody else's event: whether
        // an id exists is not something the URL should answer.
        if (! $event instanceof Event || ! $event->isSubmittedBy($this->user())) {
            throw new NotFoundHttpException();
        }

        return $event;
    }

    private function user(): User
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
