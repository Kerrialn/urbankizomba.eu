<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\DataTransferObject\NewsletterSignupDto;
use App\Form\Type\NewsletterSignupFormType;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    /**
     * @param array{video: ?string, poster: string, credit_name: ?string, credit_url: ?string} $hero
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(
        EventRepository $eventRepository,
        CityRepository $cityRepository,
        #[Autowire('%kernel.project_dir%/public')]
        string $publicDir,
        #[Autowire('%app.hero%')]
        array $hero,
    ): Response {
        $today = new DateTimeImmutable('today');

        return $this->render('app/home.html.twig', [
            // Checked here rather than in the template: a <video> pointing at
            // a missing file is a broken hero, and a fresh checkout has none.
            'hero_video' => $hero['video'] !== null && is_file($publicDir . '/' . $hero['video']),
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
