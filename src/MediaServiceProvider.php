<?php

namespace MyListerHub\Media;

use MyListerHub\Media\Console\Commands\ConfigureS3Cors;
use MyListerHub\Media\Http\Middleware\OptimizeImages;
use MyListerHub\Media\Models\Image;
use MyListerHub\Media\Observers\ImageObserver;
use MyListerHub\Media\Services\OptimizedFilepondService;
use MyListerHub\Media\Services\StreamedFilepond;
use RahulHaque\Filepond\Services\FilepondService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
    public array $bindings = [
        FilepondService::class => OptimizedFilepondService::class,
    ];

    public array $singletons = [
        'filepond' => StreamedFilepond::class,
    ];

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('media')
            ->hasConfigFile()
            ->hasRoute('media')
            ->hasMigration('create_videos_table')
            ->hasMigration('create_videoables_table')
            ->hasCommand(ConfigureS3Cors::class);
    }

    public function packageBooted(): void
    {
        $imageClass = config('media.models.image', Image::class);
        $imageClass::observe(ImageObserver::class);

        // Add middleware alias
        $router = $this->app->make('router');
        $router->aliasMiddleware('optimize-images', OptimizeImages::class);
    }
}
