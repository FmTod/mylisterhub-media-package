<?php

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use MyListerHub\Media\Http\Middleware\OptimizeImages;

beforeEach(function () {
    // Create a test route with the middleware
    Route::post('/test-upload', function () {
        $file = request()->file('image');

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $file->getRealPath(),
        ]);
    })->middleware(OptimizeImages::class);
});

it('processes and optimizes uploaded images', function () {
    // Create a test image (100x100 PNG)
    $image = UploadedFile::fake()->image('test-image.png', 100, 100);

    $response = $this->postJson('/test-upload', [
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    // The filename should be changed to .webp
    expect($data['filename'])->toBe('test-image.webp')
        ->and($data['extension'])->toBe('webp');
});

it('converts jpg images to webp', function () {
    // Create a test JPG image
    $image = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $response = $this->postJson('/test-upload', [
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['filename'])->toBe('photo.webp')
        ->and($data['extension'])->toBe('webp');
});

it('converts jpeg images to webp', function () {
    // Create a test JPEG image
    $image = UploadedFile::fake()->image('picture.jpeg', 150, 150);

    $response = $this->postJson('/test-upload', [
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['filename'])->toBe('picture.webp')
        ->and($data['extension'])->toBe('webp');
});

it('resizes large images to max dimension', function () {
    config()->set('media.storage.images.max_dimension', 500);

    // Create a large test image (3000x2000)
    $image = UploadedFile::fake()->image('large-image.png', 3000, 2000);

    $response = $this->postJson('/test-upload', [
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    // The file should be processed and converted to webp
    expect($data['filename'])->toBe('large-image.webp')
        ->and($data['extension'])->toBe('webp');
});

it('does not process non-image files', function () {
    // Create a test PDF file
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->postJson('/test-upload', [
        'image' => $file,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    // PDF should not be changed
    expect($data['filename'])->toBe('document.pdf')
        ->and($data['extension'])->toBe('pdf');
});

it('processes multiple images in a single request', function () {
    Route::post('/test-upload-multiple', function () {
        $files = request()->file('images');
        $results = [];

        foreach ($files as $file) {
            $results[] = [
                'filename' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
            ];
        }

        return response()->json($results);
    })->middleware(OptimizeImages::class);

    $image1 = UploadedFile::fake()->image('first.png', 100, 100);
    $image2 = UploadedFile::fake()->image('second.jpg', 200, 200);

    $response = $this->postJson('/test-upload-multiple', [
        'images' => [$image1, $image2],
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data)->toHaveCount(2)
        ->and($data[0]['filename'])->toBe('first.webp')
        ->and($data[0]['extension'])->toBe('webp')
        ->and($data[1]['filename'])->toBe('second.webp')
        ->and($data[1]['extension'])->toBe('webp');
});

it('skips optimization when config is disabled', function () {
    config()->set('media.storage.images.optimize', false);

    $image = UploadedFile::fake()->image('test.png', 100, 100);

    $response = $this->postJson('/test-upload', [
        'image' => $image,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    // Should keep original extension when optimization is disabled
    expect($data['filename'])->toBe('test.png')
        ->and($data['extension'])->toBe('png');
});

it('only processes files with allowed mime types', function () {
    // Create a GIF image (not in allowed_mimes by default)
    $gif = UploadedFile::fake()->create('animated.gif', 100, 'image/gif');

    $response = $this->postJson('/test-upload', [
        'image' => $gif,
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    // GIF should not be processed since it's not in allowed_mimes
    expect($data['filename'])->toBe('animated.gif')
        ->and($data['extension'])->toBe('gif');
});

it('handles invalid files gracefully in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Create an invalid file
    $invalidFile = UploadedFile::fake()->create('invalid.png', 0);

    $response = $this->postJson('/test-upload', [
        'image' => $invalidFile,
    ]);

    // Should not throw an error, just pass the file through
    $response->assertSuccessful();
});
