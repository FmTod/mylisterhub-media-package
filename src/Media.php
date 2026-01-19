<?php

namespace MyListerHub\Media;

use Exception;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\UnableToCheckFileExistence;
use MyListerHub\Media\DataObjects\ProcessedImage;
use MyListerHub\Media\Models\Image;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image as SpatieImage;

class Media
{
    /**
     * Process an image (resize, convert to WebP, optimize) and save to destination path.
     *
     * @throws \Spatie\Image\Exceptions\CouldNotLoadImage
     */
    public function processImage(string $sourcePath, string $filename, ?string $destinationPath = null, ?bool $optimize = null): ProcessedImage
    {
        $optimize = $optimize ?? config('media.storage.images.optimize', true);
        $maxDimension = config('media.storage.images.max_dimension', 2000);

        if ($destinationPath === null) {
            $destinationPath = $sourcePath;
        }

        // Load the image
        $image = SpatieImage::load($sourcePath);

        // Get original dimensions
        $originalWidth = $image->getWidth();
        $originalHeight = $image->getHeight();

        $processedFilename = $filename;

        if ($optimize) {
            // Resize if image exceeds max dimension
            if ($image->getWidth() > $maxDimension || $image->getHeight() > $maxDimension) {
                $image->fit(Fit::Max, $maxDimension, $maxDimension);
            }

            // Change extension to .webp
            $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
            $nameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
            $processedFilename = "{$nameWithoutExtension}.webp";

            // If the destination path has the extension, replace it with .webp
            if (Str::endsWith($destinationPath, $fileExtension)) {
                $destinationPath = (string) Str::of($destinationPath)->beforeLast($fileExtension)->append('.webp');
            }

            // Format the image as WebP
            $image->format('webp');
            $image->optimize();
        }

        // Save the processed image to the destination path
        $image->save($destinationPath);

        return new ProcessedImage(
            path: $destinationPath,
            filename: $processedFilename,
            width: $image->getWidth(),
            height: $image->getHeight(),
            originalWidth: $originalWidth,
            originalHeight: $originalHeight,
        );
    }

    /**
     * Process and store an image (resize if needed, optimize if requested, save to disk).
     *
     * @throws \Spatie\Image\Exceptions\CouldNotLoadImage
     */
    public function processAndStoreImage(string $sourcePath, string $destinationName, ?string $disk = null, ?bool $optimize = null): ProcessedImage
    {
        $path = config('media.storage.images.path', 'media/images');

        if (is_null($disk)) {
            $disk = (string) config('media.storage.images.disk', 'public');
        }

        // Load and process the image
        $result = $this->processImage($sourcePath, $destinationName, optimize: $optimize);

        // Use stream to avoid loading an entire file into memory
        $stream = fopen($result->path, 'rb');
        Storage::disk($disk)->put("{$path}/{$result->filename}", $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        @unlink($result->path);

        return new ProcessedImage(
            path: "{$path}/{$result->filename}",
            filename: $result->filename,
            width: $result->width,
            height: $result->height,
            originalWidth: $result->originalWidth,
            originalHeight: $result->originalHeight,
        );
    }

    /**
     * Create a new image from a file.
     */
    public function createImageFromFile(UploadedFile|File $file, ?string $name = null, ?string $disk = null, ?bool $optimize = null): Image
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file->getPathname();

        if (is_null($name) || $name === '') {
            $name = sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName());
        }

        $result = $this->processAndStoreImage($filePath, $name, $disk, $optimize);

        $imageClass = config('media.models.image', Image::class);

        return $imageClass::create([
            'source' => $result->filename,
            'name' => $result->filename,
            'width' => $result->width,
            'height' => $result->height,
        ]);
    }

    /**
     * Create a new image from an url.
     */
    public function createImageFromUrl(string $url, ?string $name = null, bool $upload = false, ?string $disk = null, ?bool $optimize = null): Image
    {
        if (is_null($name) || $name === '') {
            $name = $this->getFilenameFromUrl($url);

            throw_if(! $name, InvalidArgumentException::class, 'Could not guess the name of the image. Please provide a filename.');
        }

        $imageClass = config('media.models.image', Image::class);
        $dynamicUrl = Str::isMatch('/\{([\w_]+)}/', $url);

        $finalName = $name;

        if ($upload) {
            $tempPath = tempnam(sys_get_temp_dir(), 'media_url_');

            // Use stream to download the file instead of loading into memory
            $sourceStream = fopen($url, 'rb');
            $destStream = fopen($tempPath, 'wb');

            if ($sourceStream && $destStream) {
                stream_copy_to_stream($sourceStream, $destStream);
                fclose($sourceStream);
                fclose($destStream);
            }

            $result = $this->processAndStoreImage($tempPath, $name, $disk, $optimize);
            $dimensions = ['width' => $result->width, 'height' => $result->height];
            $finalName = $result->filename;

            @unlink($tempPath);
        } else {
            $dimensions = $this->getImageDimensions($url);
        }

        return $imageClass::create([
            'name' => $finalName,
            'source' => $upload ? $finalName : $url,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'dynamic' => $dynamicUrl,
        ]);
    }

    /**
     * Get the url of an image.
     */
    public function getImageUrl(string $source): string
    {
        if (Str::isMatch('/^http(s)?:\/\//', $source)) {
            return $source;
        }

        $path = config('media.storage.images.path', 'media/images');
        $disk = config('media.storage.images.disk', 'public');
        $name = rawurlencode($source);

        return Storage::disk($disk)->url("{$path}/{$name}");
    }

    public function getImageContent(string $source): string
    {
        if (Str::isMatch('/^http(s)?:\/\//', $source)) {
            return file_get_contents($source);
        }

        $path = config('media.storage.images.path', 'media/images');
        $disk = config('media.storage.images.disk', 'public');
        $name = rawurlencode($source);

        return Storage::disk($disk)->get("{$path}/{$name}");
    }

    public function getFilenameFromUrl(string $url): string
    {
        return (string) Str::of($url)
            ->afterLast('/')
            ->basename()
            ->before('?')
            ->trim()
            ->prepend('_')
            ->prepend(now()->getTimestamp());
    }

    /**
     * Get the file size of an image.
     */
    public function getImageSize(string $source): int
    {
        if (Str::isMatch('/^http(s)?:\/\//', $source)) {
            $headers = get_headers($source, 1);

            if (isset($headers['Content-Length'])) {
                return (int) $headers['Content-Length'];
            }

            return 0;
        }

        $path = config('media.storage.images.path', 'media/images');
        $disk = config('media.storage.images.disk', 'public');
        $name = rawurlencode($source);

        $filePath = "{$path}/{$name}";

        try {
            $exist = Storage::disk($disk)->exists($filePath);
        } catch (UnableToCheckFileExistence) {
            $exist = false;
        }

        if (! $exist) {
            return 0;
        }

        return Storage::disk($disk)->size($filePath);
    }

    /**
     * Get image dimensions from the source without downloading the full image.
     */
    public function getImageDimensions(string $source): ?array
    {
        if (! Str::isMatch('/^http(s)?:\/\//', $source)) {
            $path = config('media.storage.images.path', 'media/images');
            $disk = config('media.storage.images.disk', 'public');
            $filepath = Storage::disk($disk)->path("{$path}/{$source}");
            $image = SpatieImage::load($filepath);

            return [
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
            ];
        }

        try {
            // Create a stream context that limits the amount of data read
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Range: bytes=0-32768\r\n", // Only read the first 32 KB
                    'ignore_errors' => true,
                ],
            ]);

            // getimagesize can work with URLs and only reads the necessary bytes
            $dimensions = @getimagesize($source, $context);

            if ($dimensions === false) {
                return null;
            }

            return [
                'width' => $dimensions[0],
                'height' => $dimensions[1],
            ];
        } catch (Exception) {
            return null;
        }
    }
}
