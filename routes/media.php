<?php

use Illuminate\Support\Facades\Route;
use MyListerHub\Media\Http\Controllers\ImageController;
use MyListerHub\Media\Http\Controllers\MediaProxyController;
use MyListerHub\Media\Http\Controllers\VideoController;

$options = config('media.route_options');

Route::group($options, function () {
    Route::post('videos/upload', [VideoController::class, 'upload'])->name('videos.upload');
    Route::apiResource('videos', VideoController::class);

    Route::prefix('images')->name('images.')->group(function () {
        Route::get('proxy', [MediaProxyController::class, 'proxy'])->name('proxy');
        Route::post('upload', [ImageController::class, 'upload'])->name('upload');
        Route::prefix('batch')->name('batch.')->group(function () {
            Route::post('rotate', [ImageController::class, 'batchRotate'])->name('rotate');
            Route::post('store-file', [ImageController::class, 'batchStoreFile'])->name('store-file');
        });
    });
    Route::apiResource('images', ImageController::class);
});
