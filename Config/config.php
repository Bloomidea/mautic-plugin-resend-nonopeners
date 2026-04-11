<?php

declare(strict_types=1);

return [
    'name'        => 'Resend to Non-Openers',
    'description' => 'Resend segment emails to contacts who did not open them, with automatic segment cloning and translation handling.',
    'version'     => '1.0.0',
    'author'      => 'Mautic Community',

    'routes' => [
        'main' => [
            'mautic_resend_nonopeners_modal' => [
                'path'       => '/resend-nonopeners/{objectId}',
                'controller' => 'MauticPlugin\MauticResendNonOpenersBundle\Controller\ResendNonOpenersController::modalAction',
            ],
            'mautic_resend_nonopeners_execute' => [
                'path'       => '/resend-nonopeners/{objectId}/execute',
                'controller' => 'MauticPlugin\MauticResendNonOpenersBundle\Controller\ResendNonOpenersController::resendAction',
                'method'     => 'POST',
            ],
        ],
        'api' => [
            'mautic_api_resend_nonopeners' => [
                'path'       => '/resend-nonopeners/{id}',
                'controller' => 'MauticPlugin\MauticResendNonOpenersBundle\Controller\Api\ResendApiController::resendAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'services' => [
        'events' => [],
        'other'  => [],
    ],
];
