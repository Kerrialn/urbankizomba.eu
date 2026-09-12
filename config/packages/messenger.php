<?php

declare(strict_types=1);

use App\Message\Message\LaunchCampaignMessage;
use App\Message\Message\ProcessImportMessage;
use App\Message\Message\SendPatientMessage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Notifier\Message\ChatMessage;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'failure_transport' => 'failed',
            'transports' => [
                'async' => [
                    'dsn' => '%env(MESSENGER_TRANSPORT_DSN)%',
                    'options' => [
                        'use_notify' => true,
                        'check_delayed_interval' => 60000,
                        // Default is 3600. A worker killed mid-import would leave
                        // the practice watching a spinner for an hour before the
                        // message became available again; two minutes is long
                        // enough for the longest legitimate import.
                        'redeliver_timeout' => 120,
                    ],
                    'retry_strategy' => [
                        'max_retries' => 3,
                        'multiplier' => 2,
                    ],
                ],
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'default_bus' => 'messenger.bus.default',
            'buses' => [
                'messenger.bus.default' => [
                ],
            ],
            'routing' => [
                SendEmailMessage::class => 'async',
                ChatMessage::class => 'async',
                // SmsMessage is deliberately NOT routed async. Campaign sending
                // queues one message per recipient of its own, and letting the
                // texter queue again would hide the provider's response — which
                // is the only way to know whether a patient was actually texted.
                ProcessImportMessage::class => 'async',
                LaunchCampaignMessage::class => 'async',
                SendPatientMessage::class => 'async',
            ],
        ],
    ]);
};
