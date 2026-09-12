<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\Subscriber;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\SubscriberRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The back-office: a review queue and the tables behind it.
 *
 * Guarded twice, on purpose. The IsGranted here is the real gate; the
 * access_control rule for ^/admin in security.php is what stops a CRUD
 * controller added later from being public by omission.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly SubscriberRepository $subscriberRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'pending' => $this->eventRepository->countPending(),
            'subscribers' => $this->subscriberRepository->countConfirmed(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('urbankizomba.eu')
            ->setFaviconPath('images/logo-64.png')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Calendar');
        yield MenuItem::linkToCrud('Events', 'fa fa-calendar', Event::class);
        yield MenuItem::linkToCrud('Cities', 'fa fa-map-marker', City::class);

        yield MenuItem::section('People');
        yield MenuItem::linkToCrud('Subscribers', 'fa fa-envelope', Subscriber::class);
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Back to the site', 'fa fa-arrow-left', 'app_home');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->displayUserAvatar(false);
    }
}
