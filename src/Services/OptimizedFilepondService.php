<?php

namespace MyListerHub\Media\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MyListerHub\Media\Facades\Media;
use RahulHaque\Filepond\Services\FilepondService;

/**
 * Optimized Filepond Service Decorator
 *
 * This service extends the base FilepondService to automatically process image files
 * during upload. It acts as a decorator that intercepts uploaded files and applies
 * image optimization before they are stored.
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
 * @see \RahulHaque\Filepond\Services\FilepondService
 */
class OptimizedFilepondService extends FilepondService
{
    /**
     * @return \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]|null
     */
    protected function getUploadedFile(Request $request): array|UploadedFile|null
    {
        /** @var \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]|null $file */
        $file = parent::getUploadedFile($request);

        if ($file === null) {
            return null;
        }

        if (is_array($file)) {
            return array_map(fn($f) => $this->processImageFile($f), $file);
        }

        return $this->processImageFile($file);
    }

    /**
     * Process a file if it's an image, otherwise return as-is
     */
    protected function processImageFile(UploadedFile $file): UploadedFile
    {
        $allowedMimes = config('media.storage.images.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']);
        $optimize = config('media.storage.images.optimize', true);

        if (!$optimize || !$file->isValid()) {
            return $file;
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());

        // Only process if it's an image file
        if (!in_array($extension, $allowedMimes, true)) {
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
        } catch (\Exception $e) {
            if (app()->environment('testing')) {
                throw $e;
            }

            report($e);

            // Return the original file if processing fails
            return $file;
        }
    }
}
