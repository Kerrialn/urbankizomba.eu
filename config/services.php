<?php

declare(strict_types=1);

use App\Validator\Constraint\CompanyNumberConstraintValidator;
use App\Validator\Constraint\TurnstileConstraintValidator;
use App\Verification\Contract\VerificationSenderInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // Collected by VerificationSenderResolver. Tagging by interface here rather
    // than per-class means a new channel (SMS, WhatsApp) is registered simply by
    // implementing the interface.
    $services->instanceof(VerificationSenderInterface::class)
        ->tag('app.verification_sender');

    $services->load('App\\', __DIR__ . '/../src/')
        ->exclude([
            __DIR__ . '/../src/DependencyInjection/',
            __DIR__ . '/../src/Entity/',
            __DIR__ . '/../src/Kernel.php',
        ]);

    $services->set(StripeClient::class)
        ->args([[
            'api_key' => param(name: 'env(STRIPE_PRIVATE_KEY)'),
        ]]);

    $services
        ->set(CompanyNumberConstraintValidator::class)
        ->tag('validator.constraint_validator');

    $services
        ->set(TurnstileConstraintValidator::class)
        ->tag('validator.constraint_validator');
};
