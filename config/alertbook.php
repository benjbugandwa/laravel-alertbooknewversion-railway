<?php

return [
    'documentation' => [
        'driver' => env('ALERTBOOK_DOCUMENTATION_DRIVER', 'local'),
        'local_path' => env('ALERTBOOK_DOCUMENTATION_PATH', 'C:/DATA/PROJET ALERTBOOK 2026/Documentation'),
        'disk' => env('ALERTBOOK_DOCUMENTATION_DISK', 's3'),
        'prefix' => trim((string) env('ALERTBOOK_DOCUMENTATION_PREFIX', 'documentation/videos'), '/'),
        'temporary_url_ttl' => (int) env('ALERTBOOK_DOCUMENTATION_URL_TTL', 3600),
    ],
];
