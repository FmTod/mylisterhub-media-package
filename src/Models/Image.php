<?php

namespace MyListerHub\Media\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MyListerHub\Media\Database\Factories\ImageFactory;
use MyListerHub\Media\Facades\Media;

class Image extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'source',
        'width',
        'height',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'url',
    ];

    /**
     * Create a new factory instance for the model.
     */
    public static function newFactory(): ImageFactory
    {
        return new ImageFactory;
    }

    /**
     * Create a new image from a file.
     */
    public static function createFromFile(UploadedFile|File $file, ?string $name = null, ?string $disk = null): static
    {
        return Media::createImageFromFile($file, $name, $disk);
    }

    /**
     * Create a new image from an url.
     */
    public static function createFromUrl(string $url, ?string $name = null, bool $upload = false, ?string $disk = null): static
    {
        return Media::createImageFromUrl($url, $name, $upload, $disk);
    }

    /**
     * Store the image file to the specified disk and path.
     */
    public function storeFile(?string $filename = null, ?string $disk = null, bool $optimize = true): bool
    {
        if (! Str::startsWith($this->source, 'http://') && ! Str::startsWith($this->source, 'https://')) {
            throw new InvalidArgumentException('The source must be a valid URL starting with http(s)://');
        }

        // Get the path where images should be stored from the configuration
        $path = config('media.storage.images.path', 'media/images');

        // If no disk is provided, use the default disk from the configuration
        if (is_null($disk)) {
            $disk = (string) config('media.storage.images.disk', 'public');
        }

        // If no filename is provided, generate one based on the source URL and current timestamp
        if (is_null($filename) || $filename === '') {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $filename = throw_unless(
                condition: $this->name ?? (string) Str::of($this->source)
                ->afterLast('/')
                ->before('?')
                ->trim()
                ->prepend('_')
                ->prepend(now()->getTimestamp()),
                exception: new InvalidArgumentException('Could not guess the name of the image. Please provide a filename.')
            );
        }

        // Extract the file extension and name from the provided filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'webp';
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Get the content of the image from the source URL
        $content = file_get_contents($this->source);
        if ($content === false) {
            throw new InvalidArgumentException('Could not download the image from the source URL.');
        }

        // Create a temporary file to store the image content
        $tempPath = tempnam(sys_get_temp_dir(), 'media_');
        file_put_contents($tempPath, $content);

        // Load the image using Spatie's Image library
        $image = \Spatie\Image\Image::load($tempPath);

        // Optimize the image if required
        if ($optimize) {
            $extension = 'webp'; // Ensure the extension is set to webp for optimized images
            $image->optimize();
        }

        // Save the image with the specified extension
        $image->save("{$tempPath}.{$extension}");

        // Store the image in the specified disk and path
        Storage::disk($disk)->writeStream("{$path}/{$name}", fopen("{$tempPath}.{$extension}", 'rb'));

        // Update the model with the new source and dimensions
        return $this->update([
            'name' => $name,
            'source' => $name,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
        ]);
    }

    /**
     * Build the URL for the image.
     */
    public function buildUrl(bool $bustCache = false): string
    {
        return $this->url . ($bustCache ? (parse_url($this->url, PHP_URL_QUERY) ? '&' : '?') . '_t=' . now()->getTimestamp() : '');
    }

    /**
     * Get the name of the image.
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn ($value, $attributes) => empty($attributes['name'])
                ? Str::before(Str::afterLast($attributes['source'], '/'), '?')
                : $attributes['name'],
        );
    }

    /**
     * Get the url of the image.
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn ($value, $attributes): string => isset($attributes['source'])
                ? Media::getImageUrl($attributes['source'])
                : '',
        );
    }

    /**
     * Get the size of the image.
     */
    protected function size(): Attribute
    {
        return Attribute::get(
            fn ($value, $attributes): int => isset($attributes['source'])
                ? Media::getImageSize($attributes['source'])
                : 0,
        );
    }
}
