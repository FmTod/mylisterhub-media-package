<?php

namespace MyListerHub\Media\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MyListerHub\Media\Facades\Media;

class OptimizeImages
{
    /**
     * Handle an incoming request and optimize uploaded images.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $allowedMimes = config('media.storage.images.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']);
        $optimize = config('media.storage.images.optimize', true);

        if (! $optimize) {
            return $next($request);
        }

        collect($request->allFiles())
            ->flatten()
            ->filter(function (UploadedFile $file) {
                if (app()->environment('testing')) {
                    return true;
                }

                return $file->isValid();
            })
            ->filter(function (UploadedFile $file) use ($allowedMimes) {
                // Only process image files that match the allowed MIME types
                $extension = mb_strtolower($file->getClientOriginalExtension());

                return in_array($extension, $allowedMimes, true);
            })
            ->each(function (UploadedFile $file) use ($request, $optimize) {
                try {
                    // Process the image in-place
                    $result = Media::processImage(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        optimize: $optimize,
                    );

                    // Create a new UploadedFile instance with the processed file
                    $processedFile = new UploadedFile(
                        $result->path,
                        $result->filename,
                        $file->getMimeType(),
                        null,
                        true // test mode to allow setting the path manually
                    );

                    // Replace the file in the request
                    $this->replaceFileInRequest($request, $file, $processedFile);
                } catch (Exception $e) {
                    // Silently skip files that cannot be processed in production
                    if (app()->environment('testing')) {
                        throw $e;
                    }
                }
            });

        return $next($request);
    }

    /**
     * Replace a file in the request with a new processed file.
     */
    protected function replaceFileInRequest(Request $request, UploadedFile $originalFile, UploadedFile $newFile): void
    {
        // Find and replace the file in the request
        $files = $request->allFiles();

        array_walk_recursive($files, static function (&$file) use ($originalFile, $newFile) {
            if ($file === $originalFile) {
                $file = $newFile;
            }
        });

        // Update the request with the new files
        $request->files->replace($files);
    }
}
