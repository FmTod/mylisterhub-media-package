<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MyListerHub\Media\Facades\Media;
use MyListerHub\Media\Models\Image;

beforeEach(function () {
    Storage::fake(config('media.storage.images.disk', 'public'));
});

it('does not resize images smaller than 2000px', function () {
    $file = UploadedFile::fake()->image('small.jpg', 800, 600);

    $image = Media::createImageFromFile($file);

    expect($image->width)->toBe(800)
        ->and($image->height)->toBe(600);
});

it('keeps images at exactly 2000px unchanged', function () {
    $file = UploadedFile::fake()->image('limit.jpg', 2000, 2000);

    $image = Media::createImageFromFile($file);

    expect($image->width)->toBe(2000)
        ->and($image->height)->toBe(2000);
});

it('resizes images created via createFromFile static method', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $image = Image::createFromFile($file);

    expect($image->width)->toBe(800)
        ->and($image->height)->toBe(600);
});

it('supports optimization parameter', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $image = Image::createFromFile($file, optimize: true);

    expect($image->width)->toBe(800)
        ->and($image->height)->toBe(600);
});
