<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Library media (e-books / audiobooks)
        |--------------------------------------------------------------------------
        |
        | Not web-accessible — downloads only through LibraryController after
        | access checks. Covers stay on the `public` disk.
        |
        */
        'library_media' => [
            'driver' => 'local',
            'root' => storage_path('app/library-media'),
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin-uploaded videos (not publicly linked; streamed via controller)
        |--------------------------------------------------------------------------
        */
        'video_uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/video-uploads'),
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        's3-private' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET', env('AWS_BUCKET')),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    /*
    | Max upload size for admin video files (kilobytes). PHP upload_max_filesize / post_max_size must allow this.
    */
    'video_upload_max_kb' => (int) env('VIDEO_UPLOAD_MAX_KB', 1024000),
];
