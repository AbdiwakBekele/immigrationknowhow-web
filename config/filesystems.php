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
        | Not web-accessible — reads/listens only through LibraryController after
        | access checks. Set LIBRARY_MEDIA_DRIVER=s3 to store these binaries in
        | a private S3 bucket. Covers stay on the `public` disk.
        |
        */
        'library_media' => [
            'driver' => env('LIBRARY_MEDIA_DRIVER', 'local'),
            'root' => env('LIBRARY_MEDIA_DRIVER', 'local') === 's3'
                ? env('LIBRARY_MEDIA_PREFIX', 'library-media')
                : storage_path('app/library-media'),
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

        /*
        |--------------------------------------------------------------------------
        | Legacy local library media
        |--------------------------------------------------------------------------
        |
        | Fallback for files uploaded before LIBRARY_MEDIA_DRIVER was switched to
        | S3. New uploads still use `library_media`.
        |
        */
        'library_media_local' => [
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
