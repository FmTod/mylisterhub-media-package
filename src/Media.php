<?php

namespace MyListerHub\Media;

use Exception;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\UnableToCheckFileExistence;
use MyListerHub\Media\Models\Image;
use Spatie\Image\Image as SpatieImage;

class Media
{
    /**
     * Create a new image from a file.
     */
    public function createImageFromFile(UploadedFile|File $file, ?string $name = null, ?string $disk = null): Image
    {
        $path = config('media.storage.images.path', 'media/images');
        $image = SpatieImage::load($file);

        if (is_null($name) || $name === '') {
            $name = sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName());
        }

        if (is_null($disk)) {
            $disk = (string) config('media.storage.images.disk', 'public');
        }

        Storage::disk($disk)->putFileAs($path, $file, $name);

        $imageClass = config('media.models.image', Image::class);

        return $imageClass::create([
            'source' => $name,
            'name' => $name,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
        ]);
    }

    /**
     * Create a new image from an url.
     */
    public function createImageFromUrl(string $url, ?string $name = null, bool $upload = false, ?string $disk = null): Image
    {
        $path = config('media.storage.images.path', 'media/images');

        if (is_null($disk)) {
            $disk = (string) config('media.storage.images.disk', 'public');
        }

        if (is_null($name) || $name === '') {
            $name = (string) Str::of($url)
                ->afterLast('/')
                ->before('?')
                ->trim()
                ->prepend('_')
                ->prepend(now()->getTimestamp());

            throw_if(! $name, InvalidArgumentException::class, 'Could not guess the name of the image. Please provide a filename.');
        }

        $imageClass = config('media.models.image', Image::class);
        $dynamicUrl = Str::isMatch('/\{([\w_]+)}/', $url);

        if ($upload) {
            $file = file_get_contents($url);
            Storage::disk($disk)->put("{$path}/{$name}", $file);
        }

        $dimensions = $this->getImageDimensions($url);

        return $imageClass::create([
            'name' => $name,
            'source' => $upload ? $name : $url,
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
        if (Str::isMatch('/http(s)?:\/\//', $source)) {
            return $source;
        }

        $path = config('media.storage.images.path', 'media/images');
        $disk = config('media.storage.images.disk', 'public');
        $name = rawurlencode($source);

        return Storage::disk($disk)->url("{$path}/{$name}");
    }

    public function getImageContent(string $source): string
    {
        if (Str::isMatch('/http(s)?:\/\//', $source)) {
            return file_get_contents($source);
        }

        $path = config('media.storage.images.path', 'media/images');
        $disk = config('media.storage.images.disk', 'public');
        $name = rawurlencode($source);

        return Storage::disk($disk)->get("{$path}/{$name}");
    }

    /**
     * Get the file size of an image.
     */
    public function getImageSize(string $source): int
    {
        if (Str::isMatch('/http(s)?:\/\//', $source)) {
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
        if (! Str::isMatch('/http(s)?:\/\//', $source)) {
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
    }}
