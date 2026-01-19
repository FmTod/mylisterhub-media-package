<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Options
    |--------------------------------------------------------------------------
    |
    | Define the options to use for media routes.
    |
    */
    'route_options' => [
        'as' => 'media.',
        'prefix' => 'media',
        'middleware' => ['web', 'auth'],
    ],

    /*
     * --------------------------------------------------------------------------
     * Models
     * --------------------------------------------------------------------------
     *
     * Define the models to use for media.
     *
     */
    'models' => [
        'image' => \MyListerHub\Media\Models\Image::class,
        'video' => \MyListerHub\Media\Models\Video::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Define storage disk and path for media.
    |
    */
    'storage' => [
        'images' => [
            'disk' => 'public',
            'path' => 'media/images',
            'max_size' => env('MEDIA_IMAGE_MAX_SIZE', 10240),
            'optimize' => env('MEDIA_IMAGE_OPTIMIZE', true),
            'max_dimension' => env('MEDIA_IMAGE_MAX_DIMENSION', 2000),
            'allowed_mimes' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
        ],

        'videos' => [
            'disk' => 'public',
            'path' => 'media/videos',
            'max_size' => env('MEDIA_VIDEO_MAX_SIZE', 51200),
            'allowed_mimes' => [
                'mp4',
                'avi',
                'mov',
                'mkv',
                'webm',
            ],
        ],
    ],
];
