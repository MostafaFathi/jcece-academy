<?php

return [
    'commerce' => [
        'currency' => env('JCEC_COMMERCE_CURRENCY'),
        'payment_proof_disk' => env('JCEC_PAYMENT_PROOF_DISK', 'local'),
        'payment_proof_max_kilobytes' => (int) env('JCEC_PAYMENT_PROOF_MAX_KILOBYTES', 5120),
    ],

    'assignments' => [
        'file_disk' => env('JCEC_ASSIGNMENT_FILE_DISK', 'local'),
        'attachment_max_kilobytes' => (int) env('JCEC_ASSIGNMENT_ATTACHMENT_MAX_KILOBYTES', 20480),
        'submission_file_max_kilobytes' => (int) env('JCEC_ASSIGNMENT_SUBMISSION_FILE_MAX_KILOBYTES', 20480),
        'submission_file_max_count' => (int) env('JCEC_ASSIGNMENT_SUBMISSION_FILE_MAX_COUNT', 5),
    ],

    'certificates' => [
        'pdf_disk' => env('JCEC_CERTIFICATE_PDF_DISK', 'local'),
        'logo_path' => public_path('assets/images/logo-1.png'),
    ],

    'support' => [
        'attachment_disk' => env('JCEC_SUPPORT_ATTACHMENT_DISK', 'local'),
        'attachment_max_kilobytes' => (int) env('JCEC_SUPPORT_ATTACHMENT_MAX_KILOBYTES', 10240),
        'attachment_max_count' => (int) env('JCEC_SUPPORT_ATTACHMENT_MAX_COUNT', 5),
        'attachment_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'doc', 'docx'],
    ],

    'development_admin' => [
        'name' => env('JCEC_DEV_ADMIN_NAME', 'JCEC Local Admin'),
        'email' => env('JCEC_DEV_ADMIN_EMAIL', 'admin@jcec.test'),
        'password' => env('JCEC_DEV_ADMIN_PASSWORD', 'password'),
    ],
];
