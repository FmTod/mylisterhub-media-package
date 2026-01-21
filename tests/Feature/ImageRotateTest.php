<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MyListerHub\Media\Facades\Media;
use MyListerHub\Media\Models\Image;
use Spatie\Image\Enums\Orientation;

beforeEach(function () {
    Storage::fake(config('media.storage.images.disk', 'public'));
});

it('rotates images left (-90 degrees) overwriting the original', function () {
    $file = UploadedFile::fake()->image('sample.jpg', 120, 100);
    $image = Media::createImageFromFile($file);

    expect($image->width)->toBe(120)
        ->and($image->height)->toBe(100);

    $image->rotate(Orientation::RotateMinus90);

    expect($image->width)->toBe(100)
        ->and($image->height)->toBe(120);

    $disk = config('media.storage.images.disk', 'public');
    $path = trim(config('media.storage.images.path', 'media/images'), '/');
    Storage::disk($disk)->assertExists("{$path}/{$image->source}");
});

it('rotates images right (90 degrees) overwriting the original', function () {
    $file = UploadedFile::fake()->image('sample.jpg', 120, 100);
    $image = Media::createImageFromFile($file);

    expect($image->width)->toBe(120)
        ->and($image->height)->toBe(100);

    $image->rotate(Orientation::Rotate90);

    expect($image->width)->toBe(100)
        ->and($image->height)->toBe(120);

    $disk = config('media.storage.images.disk', 'public');
    $path = trim(config('media.storage.images.path', 'media/images'), '/');
    Storage::disk($disk)->assertExists("{$path}/{$image->source}");
});

it('rotates images 180 degrees keeping dimensions', function () {
    $file = UploadedFile::fake()->image('sample.jpg', 120, 100);
    $image = Media::createImageFromFile($file);

    expect($image->width)->toBe(120)
        ->and($image->height)->toBe(100);

    $image->rotate(Orientation::Rotate180);

    expect($image->width)->toBe(120)
        ->and($image->height)->toBe(100);

    $disk = config('media.storage.images.disk', 'public');
    $path = trim(config('media.storage.images.path', 'media/images'), '/');
    Storage::disk($disk)->assertExists("{$path}/{$image->source}");
});

it('throws exception when rotating remote URL images', function () {
    $image = new Image([
        'source' => 'https://example.com/image.jpg',
        'name' => 'remote-image.jpg',
        'width' => 120,
        'height' => 100,
    ]);

    expect(fn () => $image->rotate(Orientation::Rotate90))
        ->toThrow(InvalidArgumentException::class, 'Rotating remote URL images is not supported.');
});

it('throws exception when image file not found', function () {
    $image = Image::factory()->create([
        'source' => 'nonexistent.jpg',
        'width' => 120,
        'height' => 100,
    ]);

    expect(fn () => $image->rotate(Orientation::Rotate90))
        ->toThrow(InvalidArgumentException::class, 'Image file not found for rotation.');
});
