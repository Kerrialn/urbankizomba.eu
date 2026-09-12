<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The terms and the privacy policy.
 *
 * The text lives in templates rather than in the translation catalogue: these
 * are documents, edited whole, and splitting them into keyed strings makes a
 * half-edited clause easy to ship. Both are versioned by date and the date is
 * shown on the page.
 */
final class LegalController extends AbstractController
{
    public const string TERMS_VERSION = '2026-09-12';

    public const string PRIVACY_VERSION = '2026-09-12';

    #[Route('/terms', name: 'app_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig', [
            'version' => self::TERMS_VERSION,
        ]);
    }

    #[Route('/privacy', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig', [
            'version' => self::PRIVACY_VERSION,
        ]);
    }
}
