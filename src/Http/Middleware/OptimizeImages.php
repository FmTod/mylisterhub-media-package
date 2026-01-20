<?php

namespace MyListerHub\Media\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use MyListerHub\Media\Facades\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Optimize Images Middleware
 *
 * This middleware automatically processes and optimizes all image files uploaded
 * in HTTP requests before they reach the controller. It intercepts the request,
 * identifies image files, and applies optimization transformations.
 *
 * Features:
 * - Automatic image resizing to maximum dimensions
 * - WebP conversion for better compression
 * - Image optimization to reduce file size
 * - Graceful error handling with reporting
 * - Updates request with processed files
 *
 * ⚠️ WARNING - Potential Issues:
 * This middleware processes files BEFORE validation runs, which can introduce
 * several problems:
 *
 * 1. Performance Impact: All uploaded files are processed regardless of whether
 *    they will pass validation, wasting server resources.
 *
 * 2. Security Risks: Files are processed before being validated, potentially
 *    exposing the server to malicious file attacks.
 *
 * 3. Resource Abuse: Toxic users can attach files to random requests, forcing
 *    unnecessary image processing and slowing down the server.
 *
 * 4. Validation Mismatch: Files are processed before validation, so invalid
 *    uploads still consume processing time.
 *
 * 🎯 RECOMMENDED ALTERNATIVE:
 * Instead of using this middleware, call Media::processImage() directly in your
 * controllers or form requests AFTER validation passes. This ensures only valid
 * files are processed and only on legitimate upload endpoints.
 *
 * @see \MyListerHub\Media\Media::processImage() (Direct image processing method)
 */
class OptimizeImages
{
    /**
     * Handle an incoming request and optimize uploaded images.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $optimize = config('media.storage.images.optimize', true);

        if (! $optimize) {
            return $next($request);
        }

        $hasChanges = false;
        $files = $request->allFiles();
        $allowedMimes = config('media.storage.images.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']);

        // Recursively walk through files to find and convert images
        array_walk_recursive($files, static function (&$file) use ($optimize, $allowedMimes, &$hasChanges) {
            if (! $file instanceof UploadedFile
                || ! $file->isValid()
                || ! in_array(mb_strtolower($file->getClientOriginalExtension()), $allowedMimes, true)) {
                return;
            }

            try {
                $result = Media::processImage(
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    optimize: $optimize,
                );

                // Determine the MIME type based on the processed filename
                $mimeType = str_ends_with($result->name, '.webp') ? 'image/webp' : $file->getMimeType();

                // Create a new UploadedFile instance with the processed file
                $file = new UploadedFile(
                    $result->path,
                    $result->name,
                    $mimeType,
                    test: true, // test mode to allow setting the path manually
                );

                $hasChanges = true;
            } catch (Exception $e) {
                if (app()->environment('testing')) {
                    throw $e;
                }

                report($e);
            }
        });

        if ($hasChanges) {
            $request->files->replace($files);

            (function () {
                /** @var Request $this */
                unset($this->convertedFiles);
            })->call($request);
        }

        return $next($request);
    }
}
