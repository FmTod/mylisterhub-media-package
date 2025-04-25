<?php

namespace MyListerHub\Media;

use MyListerHub\Media\Models\Image;
use MyListerHub\Media\Observers\ImageObserver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
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
            ->hasMigration('create_videoables_table');
    }

    public function packageBooted(): void
    {
        $imageClass = config('media.models.image', Image::class);

        $imageClass::observe(ImageObserver::class);
    }
}
