<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Enum\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The terms and the privacy policy.
 *
 * Unlike every other page, the text lives in per-language templates rather than
 * in the translation catalogues. That is deliberate: these are documents, not
 * interface copy. A lawyer reviews and edits them whole, and splitting a
 * contract across two hundred keyed strings makes that harder and makes a
 * half-translated clause — which is worse than an untranslated button — easy to
 * ship without noticing.
 *
 * Both are versioned by date. The processing agreement a practice accepts during
 * onboarding is separate and versioned separately
 * ({@see \App\Controller\Onboarding\OnboardingController::DPA_VERSION}); these
 * two govern the site and the service around it.
 */
final class LegalController extends AbstractController
{
    /**
     * Bumped when the text changes. Shown on the page so a practice can tell
     * which version they read.
     */
    public const string TERMS_VERSION = '2026-09-10';

    public const string PRIVACY_VERSION = '2026-09-03';

    #[Route(path: [
        'cs' => '/obchodni-podminky',
        'en' => '/terms',
    ], name: 'app_terms', methods: ['GET'])]
    public function terms(Request $request): Response
    {
        return $this->render($this->template('terms', $request), [
            'version' => self::TERMS_VERSION,
        ]);
    }

    #[Route(path: [
        'cs' => '/ochrana-osobnich-udaju',
        'en' => '/privacy',
    ], name: 'app_privacy', methods: ['GET'])]
    public function privacy(Request $request): Response
    {
        return $this->render($this->template('privacy', $request), [
            'version' => self::PRIVACY_VERSION,
        ]);
    }

    /**
     * Falls back to Czech rather than 404ing on a language whose document has
     * not been written: the Czech text is the binding one, and showing it is
     * more useful than showing nothing.
     */
    private function template(string $document, Request $request): string
    {
        $locale = Locale::tryFrom($request->getLocale()) ?? Locale::DEFAULT;
        $candidate = sprintf('legal/%s.%s.html.twig', $document, $locale->value);

        return $this->container->get('twig')->getLoader()->exists($candidate)
            ? $candidate
            : sprintf('legal/%s.%s.html.twig', $document, Locale::DEFAULT->value);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'twig' => \Twig\Environment::class,
        ]);
    }
}
