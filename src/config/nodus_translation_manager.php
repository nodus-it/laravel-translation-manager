<?php

return [
    'automatic_mode' => [
        'provider' => [
            'aws' => [
                'credentials' => [
                    'key'    => env('NODUS_TRANSLATION_MANAGER_AWS_KEY', null),
                    'secret' => env('NODUS_TRANSLATION_MANAGER_AWS_SECRET', null),
                ],
            ],
        ],
    ],
];
