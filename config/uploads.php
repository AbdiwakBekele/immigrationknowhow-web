<?php

return [
    's3' => [
        'disk' => env('AWS_UPLOAD_DISK', 's3'),
        'directory' => trim((string) env('AWS_UPLOAD_DIRECTORY', 'uploads'), '/'),
        'visibility' => env('AWS_UPLOAD_VISIBILITY', 'private'),
        'max_size_kb' => (int) env('AWS_UPLOAD_MAX_KB', 1024000),
        'signed_url_ttl_minutes' => (int) env('AWS_UPLOAD_SIGNED_URL_TTL_MINUTES', 10),
        'allowed_mimes' => array_values(array_filter(array_map(
            static fn (string $mime): string => trim($mime),
            explode(',', (string) env('AWS_UPLOAD_ALLOWED_MIMES', 'jpg,jpeg,png,pdf,mp3,m4a,aac,wav,ogg'))
        ))),
    ],
    'library_covers' => [
        'disk' => env('LIBRARY_COVER_DISK', env('AWS_UPLOAD_DISK', 's3')),
        'directory' => trim((string) env('LIBRARY_COVER_DIRECTORY', 'library/covers'), '/'),
        'visibility' => env('LIBRARY_COVER_VISIBILITY', 'public'),
    ],
];
