<?php

return [
    'protected_video' => [
        'allowed_playback_hosts' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('JCEC_PROTECTED_VIDEO_PLAYBACK_HOSTS', '')))))),
    ],
    'bunny_stream' => [
        'enabled' => (bool) env('BUNNY_STREAM_ENABLED', false),
        'library_id' => (string) env('BUNNY_STREAM_LIBRARY_ID', ''),
        'api_key' => (string) env('BUNNY_STREAM_API_KEY', ''),
        'token_key' => (string) env('BUNNY_STREAM_TOKEN_KEY', ''),
        'cdn_host' => strtolower((string) env('BUNNY_STREAM_CDN_HOST', '')),
        'api_endpoint' => (string) env('BUNNY_STREAM_API_ENDPOINT', 'https://video.bunnycdn.com'),
        'playback_ttl_seconds' => (int) env('BUNNY_STREAM_PLAYBACK_TTL_SECONDS', 120),
        'upload_signature_ttl_seconds' => (int) env('BUNNY_STREAM_UPLOAD_SIGNATURE_TTL_SECONDS', 86400),
        'max_upload_megabytes' => (int) env('BUNNY_STREAM_MAX_UPLOAD_MEGABYTES', 2048),
    ],
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

    'lesson_resources' => [
        'max_kilobytes' => (int) env('JCEC_LESSON_RESOURCE_MAX_KILOBYTES', 20480),
        'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'txt', 'csv'],
    ],

    'certificates' => [
        'pdf_disk' => env('JCEC_CERTIFICATE_PDF_DISK', 'local'),
        'logo_path' => public_path('assets/images/logo-1.png'),
    ],

    'financial_documents' => [
        'pdf_disk' => env('JCEC_FINANCIAL_DOCUMENT_PDF_DISK', 'local'),
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
