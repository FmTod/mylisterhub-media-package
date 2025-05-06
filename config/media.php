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
            'max_size' => 2048,
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
            'max_size' => 10240,
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
