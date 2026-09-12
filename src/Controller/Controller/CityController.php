<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\City;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page per city: its socials and what is coming up. These are the pages
 * that rank for "urban kiz <city>", and the ones a local sends a visitor.
 */
final class CityController extends AbstractController
{
    #[Route('/cities', name: 'app_cities', methods: ['GET'])]
    public function index(CityRepository $cityRepository): Response
    {
        $rows = $cityRepository->findActiveWithCounts(new DateTimeImmutable('today'));

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['city']->getCountryName()][] = $row;
        }

        ksort($grouped);

        return $this->render('city/index.html.twig', [
            'grouped' => $grouped,
        ]);
    }

    #[Route('/cities/{slug}', name: 'app_city', requirements: [
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ], methods: ['GET'])]
    public function show(
        string $slug,
        CityRepository $cityRepository,
        EventRepository $eventRepository,
        #[Autowire('%app.social_confirmation_days%')]
        int $confirmationDays,
    ): Response {
        $city = $cityRepository->findOneBySlug($slug);

        if (! $city instanceof City) {
            throw new NotFoundHttpException();
        }

        $today = new DateTimeImmutable('today');
        $socials = $eventRepository->findSocials($city);
        $upcoming = $eventRepository->findUpcoming($today, 50, $city);

        // A city somebody typed into the form whose event was never approved
        // has a row and nothing on it. That is not a page.
        if ($socials === [] && $upcoming === []) {
            throw new NotFoundHttpException();
        }

        return $this->render('city/show.html.twig', [
            'city' => $city,
            'socials' => $socials,
            'upcoming' => $upcoming,
            'confirmation_days' => $confirmationDays,
        ]);
    }
}
