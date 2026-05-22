<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use MyListerHub\Media\Facades\Media;
use MyListerHub\Media\Http\Controllers\ImageController;
use MyListerHub\Media\Http\Requests\ImageUploadRequest;
use MyListerHub\Media\Models\Image;

it('passes validation when optimize is null', function () {
    $request = new ImageUploadRequest([
        'type' => 'files',
        'files' => [UploadedFile::fake()->image('test.jpg', 100, 100)],
    ]);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('passes validation when optimize is true', function () {
    $request = new ImageUploadRequest([
        'type' => 'files',
        'files' => [UploadedFile::fake()->image('test.jpg', 100, 100)],
        'optimize' => true,
    ]);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('passes validation when optimize is false', function () {
    $request = new ImageUploadRequest([
        'type' => 'files',
        'files' => [UploadedFile::fake()->image('test.jpg', 100, 100)],
        'optimize' => false,
    ]);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('fails validation when optimize is not a boolean', function () {
    $request = new ImageUploadRequest([
        'type' => 'files',
        'files' => [UploadedFile::fake()->image('test.jpg', 100, 100)],
        'optimize' => 'invalid',
    ]);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('optimize'))->toBeTrue();
});

it('passes optimize parameter to createImageFromFile for direct uploads', function () {
    $controller = new ImageController();
    $method = new ReflectionMethod($controller, 'processUploadedFile');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    Media::shouldReceive('createImageFromFile')
        ->once()
        ->with($file, null, null, false)
        ->andReturn(Image::factory()->make());

    $method->invoke($controller, $file, false);
});

it('defaults optimize to null for direct uploads when not provided', function () {
    $controller = new ImageController();
    $method = new ReflectionMethod($controller, 'processUploadedFile');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    Media::shouldReceive('createImageFromFile')
        ->once()
        ->with($file, null, null, null)
        ->andReturn(Image::factory()->make());

    $method->invoke($controller, $file);
});

it('passes optimize false to createImageFromFile', function () {
    $controller = new ImageController();
    $method = new ReflectionMethod($controller, 'processUploadedFile');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    Media::shouldReceive('createImageFromFile')
        ->once()
        ->with($file, null, null, false)
        ->andReturn(Image::factory()->make());

    $method->invoke($controller, $file, false);
});
