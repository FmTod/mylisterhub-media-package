<?php

use Illuminate\Support\Facades\Route;
use MyListerHub\Media\Http\Controllers\ImageController;
use MyListerHub\Media\Http\Controllers\MediaProxyController;
use MyListerHub\Media\Http\Controllers\VideoController;

$options = config('media.route_options');

Route::group($options, function () {
    Route::post('videos/upload', [VideoController::class, 'upload'])->name('videos.upload');
    Route::apiResource('videos', VideoController::class);

    Route::get('images/proxy', [MediaProxyController::class, 'proxy'])->name('images.proxy');
    Route::post('images/upload', [ImageController::class, 'upload'])->name('images.upload');
    Route::post('images/batch/rotate', [ImageController::class, 'batchRotate'])->name('images.batch-rotate');
    Route::apiResource('images', ImageController::class);
});
