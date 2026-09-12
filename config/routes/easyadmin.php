<?php

declare(strict_types=1);

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    // Pretty admin URLs (/admin/event, /admin/event/{id}). The dashboard route
    // itself comes from the #[AdminDashboard] attribute on DashboardController;
    // this loader generates one route per CRUD action from it. Not localized:
    // the back-office has one language.
    $routingConfigurator->import('.', AdminRouteLoader::ROUTE_LOADER_TYPE);
};
