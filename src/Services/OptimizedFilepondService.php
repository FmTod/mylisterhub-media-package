<?php

namespace MyListerHub\Media\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MyListerHub\Media\Facades\Media;
use RahulHaque\Filepond\Models\Filepond;
use RahulHaque\Filepond\Services\FilepondService;
use RahulHaque\Filepond\Utils\FilepondUtil;

/**
 * Optimized Filepond Service Decorator
 *
 * This service extends the base FilepondService to automatically process image files
 * during upload. It acts as a decorator that intercepts the upload in `store()` and
 * applies image optimization before the file is persisted.
 *
 * Features:
 * - Automatic image resizing to maximum dimensions (default: 2000px)
 * - WebP conversion for better compression and performance
 * - Image optimization to reduce file size
 * - Graceful fallback to original file if processing fails
 * - Pass-through for non-image files (no processing)
 *
 * Configuration is controlled via media config:
 * - media.storage.images.optimize - Enable/disable optimization
 * - media.storage.images.allowed_mimes - Supported image formats
 * - media.storage.images.max_dimension - Maximum width/height
 *
 * @see \MyListerHub\Media\Media::processImage()
 * @see FilepondService
 */
class OptimizedFilepondService extends FilepondService
{
    /**
     * Store the uploaded file, optimizing images before persistence.
     *
     * The upstream package no longer exposes a `getUploadedFile()` hook; `store()`
     * resolves and persists the file inline. We mirror the parent's persistence
     * logic here so we can swap in the optimized file. We deliberately do not
     * rewrite the request's file bag — cached request files are not reliably
     * refreshed, so the optimized file would be ignored.
     */
    public function store(Request $request): string
    {
        $file = $this->processImageFile(FilepondUtil::getUploadedFile($request), $request);

        $metadata = FilepondUtil::getMetadata($request);
        $model = config('filepond.model', Filepond::class);

        $filepond = $model::create([
            'filepath' => $file->store(
                config('filepond.temp_folder', 'filepond/temp'),
                config('filepond.temp_disk', 'local'),
            ),
            'filename' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'mimetype' => $file->getClientMimeType(),
            'metadata' => $metadata,
            'disk' => config('filepond.disk', 'public'),
            'created_by' => auth()->id(),
            'expires_at' => now()->addMinutes(config('filepond.expiration', 30)),
        ]);

        return FilepondUtil::makeFilepondId(['id' => $filepond->id]);
    }

    /**
     * Process a file if it's an image, otherwise return as-is
     */
    protected function processImageFile(UploadedFile $file, Request $request): UploadedFile
    {
        $allowedMimes = config('media.storage.images.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']);
        $optimize = $request->has('optimize')
            ? $request->boolean('optimize')
            : config('media.storage.images.optimize', true);

        if (! $optimize || ! $file->isValid()) {
            return $file;
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());

        // Only process if it's an image file
        if (! in_array($extension, $allowedMimes, true)) {
            return $file;
        }

        try {
            // Process the image in-place
            $result = Media::processImage(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                optimize: $optimize,
            );

            // Determine the MIME type based on the processed filename
            $mimeType = str_ends_with($result->name, '.webp') ? 'image/webp' : $file->getMimeType();

            // Create a new UploadedFile instance with the processed file
            return new UploadedFile(
                $result->path,
                $result->name,
                $mimeType,
                test: true, // test mode to allow setting the path manually
            );
        } catch (Exception $e) {
            if (app()->environment('testing')) {
                throw $e;
            }

            report($e);

            // Return the original file if processing fails
            return $file;
        }
    }
}
