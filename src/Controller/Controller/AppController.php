<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\DataTransferObject\NewsletterSignupDto;
use App\Form\Type\NewsletterSignupFormType;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(EventRepository $eventRepository, CityRepository $cityRepository): Response
    {
        $today = new DateTimeImmutable('today');

        return $this->render('app/home.html.twig', [
            'events' => $eventRepository->findUpcoming($today, 8),
            'cities' => $cityRepository->findActiveWithCounts($today),
            'newsletter_form' => $this->createForm(NewsletterSignupFormType::class, new NewsletterSignupDto(), [
                'action' => $this->generateUrl('app_newsletter_signup'),
            ]),
        ]);
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('app/about.html.twig');
    }
}
