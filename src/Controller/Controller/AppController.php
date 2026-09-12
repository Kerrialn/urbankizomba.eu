<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\Referral;
use App\Service\Billing\ReferralAttribution;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(Request $request, ReferralAttribution $referralAttribution): Response
    {
        return $this->render('app/landing.html.twig', [
            // Read from the session rather than the query string: the code is
            // put there by ReferralCaptureSubscriber before this runs, so the
            // banner survives a look at the pricing page and back — which is
            // most of what somebody does before they sign up.
            'referredBy' => $referralAttribution->referrerFor(
                $referralAttribution->pending($request->getSession()),
            ),
            'referralEach' => Referral::PERCENT,
        ]);
    }
}
