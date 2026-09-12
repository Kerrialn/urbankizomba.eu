<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventTypeEnum;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The calendar. Public, and the whole point of the site.
 */
final class EventController extends AbstractController
{
    private const int PER_PAGE = 24;

    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function index(
        Request $request,
        EventRepository $eventRepository,
        PaginatorInterface $paginator,
        #[MapQueryParameter]
        ?string $country = null,
        #[MapQueryParameter]
        ?string $type = null,
        #[MapQueryParameter]
        ?string $month = null,
    ): Response {
        $today = new DateTimeImmutable('today');

        // Unknown filter values are ignored rather than 404ed: a stale link
        // from a newsletter should still show the calendar.
        $typeEnum = $type !== null ? EventTypeEnum::tryFrom($type) : null;
        $monthDate = $this->parseMonth($month);
        $country = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : null;

        $pagination = $paginator->paginate(
            $eventRepository->upcomingQuery($today, $country, $typeEnum, $monthDate),
            max(1, $request->query->getInt('page', 1)),
            self::PER_PAGE,
        );

        return $this->render('event/index.html.twig', [
            'pagination' => $pagination,
            'countries' => $eventRepository->countriesWithUpcoming($today),
            'types' => EventTypeEnum::dated(),
            'months' => $this->monthOptions($today),
            'filter' => [
                'country' => $country,
                'type' => $typeEnum,
                'month' => $monthDate,
            ],
        ]);
    }

    #[Route('/socials', name: 'app_socials', methods: ['GET'])]
    public function socials(
        EventRepository $eventRepository,
        #[Autowire('%app.social_confirmation_days%')]
        int $confirmationDays,
    ): Response {
        $socials = $eventRepository->findSocials();

        // Grouped by country then city for the page, which is a directory
        // rather than a list.
        $grouped = [];

        foreach ($socials as $social) {
            $city = $social->getCity();
            $grouped[$city->getCountryName()][$city->getName()][] = $social;
        }

        ksort($grouped);

        return $this->render('event/socials.html.twig', [
            'grouped' => $grouped,
            'confirmation_days' => $confirmationDays,
        ]);
    }

    #[Route('/events/{slug}', name: 'app_event', requirements: [
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ], methods: ['GET'])]
    public function show(
        string $slug,
        EventRepository $eventRepository,
        #[Autowire('%app.social_confirmation_days%')]
        int $confirmationDays,
    ): Response {
        $event = $eventRepository->findOneBySlug($slug);

        if (! $event instanceof Event) {
            throw new NotFoundHttpException();
        }

        // The submitter can see their own listing whatever its status, so
        // they can check what they sent. Everybody else sees approved only.
        $viewer = $this->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        if (! $event->isApproved() && ! $event->isSubmittedBy($viewer) && ! $this->isGranted('ROLE_ADMIN')) {
            throw new NotFoundHttpException();
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'confirmation_days' => $confirmationDays,
            'more' => $eventRepository->findUpcoming(new DateTimeImmutable('today'), 5, $event->getCity()),
        ]);
    }

    private function parseMonth(?string $month): ?DateTimeImmutable
    {
        if ($month === null || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m', $month);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    /**
     * The next twelve months, as filter options.
     *
     * @return list<DateTimeImmutable>
     */
    private function monthOptions(DateTimeImmutable $today): array
    {
        $first = $today->modify('first day of this month');
        $months = [];

        for ($i = 0; $i < 12; ++$i) {
            $months[] = $first->modify(sprintf('+%d months', $i));
        }

        return $months;
    }
}
