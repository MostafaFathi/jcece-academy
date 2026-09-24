<?php

return [
    'commerce' => [
        'currency' => env('JCEC_COMMERCE_CURRENCY'),
        'payment_proof_disk' => env('JCEC_PAYMENT_PROOF_DISK', 'local'),
        'payment_proof_max_kilobytes' => (int) env('JCEC_PAYMENT_PROOF_MAX_KILOBYTES', 5120),
    ],

    'development_admin' => [
        'name' => env('JCEC_DEV_ADMIN_NAME', 'JCEC Local Admin'),
        'email' => env('JCEC_DEV_ADMIN_EMAIL', 'admin@jcec.test'),
        'password' => env('JCEC_DEV_ADMIN_PASSWORD', 'password'),
    ],
];
