<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MyListerHub\Media\DataObjects\ProcessedImage;
use MyListerHub\Media\Facades\Media;
use MyListerHub\Media\Services\OptimizedFilepondService;
use RahulHaque\Filepond\Factories\UploaderManager;

function createService(): OptimizedFilepondService
{
    $service = new OptimizedFilepondService(
        app(UploaderManager::class)
    );

    return $service;
}

function processImageFile(OptimizedFilepondService $service, UploadedFile $file, Request $request): UploadedFile
{
    $method = new ReflectionMethod($service, 'processImageFile');
    $method->setAccessible(true);

    return $method->invoke($service, $file, $request);
}

function createTempProcessedImage(): array
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tmpPath, 'fake image content');

    return [
        'path' => $tmpPath,
        'name' => basename($tmpPath),
    ];
}

it('uses config default true when optimize is not present in request', function () {
    config()->set('media.storage.images.optimize', true);

    $service = createService();
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $request = Request::create('/filepond', 'POST');

    $tmpImage = createTempProcessedImage();

    Media::shouldReceive('processImage')
        ->once()
        ->andReturnUsing(function () use ($tmpImage) {
            return new ProcessedImage(
                path: $tmpImage['path'],
                name: $tmpImage['name'],
                width: 100,
                height: 100,
                originalWidth: 100,
                originalHeight: 100,
            );
        });

    $result = processImageFile($service, $file, $request);

    expect($result->getClientOriginalName())->toBe($tmpImage['name']);

    @unlink($tmpImage['path']);
});

it('uses config default false when optimize is not present in request', function () {
    config()->set('media.storage.images.optimize', false);

    $service = createService();
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $request = Request::create('/filepond', 'POST');

    Media::shouldReceive('processImage')
        ->never();

    $result = processImageFile($service, $file, $request);

    expect($result)->toBe($file);
});

it('skips image processing when request optimize is false', function () {
    config()->set('media.storage.images.optimize', true);

    $service = createService();
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $request = Request::create('/filepond', 'POST', ['optimize' => false]);

    Media::shouldReceive('processImage')
        ->never();

    $result = processImageFile($service, $file, $request);

    expect($result)->toBe($file);
});

it('processes image when request optimize is true', function () {
    config()->set('media.storage.images.optimize', false);

    $service = createService();
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $request = Request::create('/filepond', 'POST', ['optimize' => true]);

    $tmpImage = createTempProcessedImage();

    Media::shouldReceive('processImage')
        ->once()
        ->andReturnUsing(function () use ($tmpImage) {
            return new ProcessedImage(
                path: $tmpImage['path'],
                name: $tmpImage['name'],
                width: 100,
                height: 100,
                originalWidth: 100,
                originalHeight: 100,
            );
        });

    $result = processImageFile($service, $file, $request);

    expect($result->getClientOriginalName())->toBe($tmpImage['name']);

    @unlink($tmpImage['path']);
});

it('skips non-image files even when optimize is true', function () {
    $service = createService();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $request = Request::create('/filepond', 'POST', ['optimize' => true]);

    Media::shouldReceive('processImage')
        ->never();

    $result = processImageFile($service, $file, $request);

    expect($result)->toBe($file);
});
