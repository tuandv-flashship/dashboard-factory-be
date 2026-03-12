<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
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
        'r2' => [
            'driver' => 's3',
            'key' => env('MEDIA_R2_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_R2_SECRET_KEY'),
            'region' => env('MEDIA_R2_REGION', 'auto'),
            'bucket' => env('MEDIA_R2_BUCKET'),
            'url' => env('MEDIA_R2_URL'),
            'endpoint' => env('MEDIA_R2_ENDPOINT'),
            'use_path_style_endpoint' => env('MEDIA_R2_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
        ],
        'do_spaces' => [
            'driver' => 's3',
            'key' => env('MEDIA_DO_SPACES_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_DO_SPACES_SECRET_KEY'),
            'region' => env('MEDIA_DO_SPACES_DEFAULT_REGION'),
            'bucket' => env('MEDIA_DO_SPACES_BUCKET'),
            'endpoint' => env('MEDIA_DO_SPACES_ENDPOINT'),
            'use_path_style_endpoint' => env('MEDIA_DO_SPACES_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
        'wasabi' => [
            'driver' => 's3',
            'key' => env('MEDIA_WASABI_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_WASABI_SECRET_KEY'),
            'region' => env('MEDIA_WASABI_DEFAULT_REGION'),
            'bucket' => env('MEDIA_WASABI_BUCKET'),
            'root' => env('MEDIA_WASABI_ROOT', '/'),
            'endpoint' => env('MEDIA_WASABI_ENDPOINT'),
            'throw' => false,
        ],
        'bunnycdn' => [
            'driver' => 'bunnycdn',
            'storage_zone' => env('MEDIA_BUNNYCDN_ZONE'),
            'hostname' => env('MEDIA_BUNNYCDN_HOSTNAME'),
            'api_key' => env('MEDIA_BUNNYCDN_KEY'),
            'region' => env('MEDIA_BUNNYCDN_REGION'),
            'throw' => false,
        ],
        'backblaze' => [
            'driver' => 's3',
            'key' => env('MEDIA_BACKBLAZE_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_BACKBLAZE_SECRET_KEY'),
            'region' => env('MEDIA_BACKBLAZE_DEFAULT_REGION'),
            'bucket' => env('MEDIA_BACKBLAZE_BUCKET'),
            'endpoint' => env('MEDIA_BACKBLAZE_ENDPOINT'),
            'use_path_style_endpoint' => env('MEDIA_BACKBLAZE_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
