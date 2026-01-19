<?php

namespace MyListerHub\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MyListerHub\Media\Database\Factories\ImageFactory;
use MyListerHub\Media\Facades\Media;
use Stringable;

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
        'dynamic',
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
    public function storeFile(?string $filename = null, ?string $disk = null): bool
    {
        if (! Str::isMatch('/http(s)?:\/\//', $this->source)) {
            throw new InvalidArgumentException('The source must be a valid URL starting with http(s)://');
        }

        // If no disk is provided, use the default disk from the configuration
        if (is_null($disk)) {
            $disk = (string) config('media.storage.images.disk', 'public');
        }

        // If no filename is provided, generate one based on the source URL and current timestamp
        if (is_null($filename) || $filename === '') {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $filename = throw_unless(
                condition: $this->name ?? Media::getFilenameFromUrl($this->source),
                exception: new InvalidArgumentException('Could not guess the name of the image. Please provide a filename.')
            );
        }

        // Extract the file extension and name from the provided filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Download the image from the source URL using streams
        $tempPath = tempnam(sys_get_temp_dir(), 'media_');

        $sourceStream = fopen($this->source, 'rb');
        if ($sourceStream === false) {
            throw new InvalidArgumentException('Could not download the image from the source URL.');
        }

        $destStream = fopen($tempPath, 'wb');
        if ($destStream === false) {
            fclose($sourceStream);
            throw new InvalidArgumentException('Could not create temporary file for image download.');
        }

        stream_copy_to_stream($sourceStream, $destStream);
        fclose($sourceStream);
        fclose($destStream);

        $result = Media::processAndStoreImage($tempPath, "{$name}.{$extension}", $disk);

        @unlink($tempPath);

        // Update the model with the new source and dimensions (using the final name which may be .webp)
        return $this->update([
            'name' => $result->name,
            'source' => $result->name,
            'width' => $result->width,
            'height' => $result->height,
        ]);
    }

    /**
     * Build the URL for the image.
     */
    public function buildUrl(bool $bustCache = false, array $data = []): string
    {
        // Process dynamic URLs by replacing placeholders
        $url = $this->dynamic
            ? preg_replace_callback('/\{([\w_]+)}/', static function ($matches) use ($data): string {
                // Skip if the placeholder doesn't exist in data
                if (! isset($data[$matches[1]])) {
                    return $matches[0];
                }

                // Skip if value isn't scalar or can't be converted to string
                if (! is_scalar($data[$matches[1]]) && ! $data[$matches[1]] instanceof Stringable) {
                    return $matches[0];
                }

                return (string) $data[$matches[1]];
            }, $this->url)
            : $this->url;

        // Add cache busting parameter if needed
        return $url.($bustCache ? (parse_url($url, PHP_URL_QUERY) ? '&' : '?').'_t='.now()->getTimestamp() : '');
    }

    /**
     * Scope a query to only include images that are not used by any model, optionally excluding a type or type and id.
     */
    public function scopeWhereNotUsed(Builder $query, Model|string|null $except = null): Builder
    {
        return $query->whereNotExists(function ($query) use ($except) {
            $query
                ->select(DB::raw(1))
                ->from('imageables')
                ->whereColumn('imageables.imageable_id', 'images.id')
                ->when($except, function (Builder $conditionalQuery) use ($except) {
                    $conditionalQuery->where(function (Builder $subQuery) use ($except) {
                        $model = is_string($except) ? $except::newModelInstance() : $except;
                        $morphType = $model->getMorphClass();

                        $subQuery
                            ->where('imageable_type', '!=', $morphType)
                            ->when(
                                value: $except instanceof Model,
                                callback: fn (Builder $q) => $q->orWhere(function (Builder $innerQuery) use ($except, $morphType) {
                                    $innerQuery
                                        ->where('imageable_type', $morphType)
                                        ->where('imageable_id', '!=', $except->getKey());
                                }),
                            );
                    });
                });
        });
    }

    /**
     * Get the name of the image.
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            static fn ($value, $attributes) => empty($attributes['name'])
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
            static fn ($value, $attributes): string => isset($attributes['source'])
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
            static fn ($value, $attributes): int => isset($attributes['source'])
                ? Media::getImageSize($attributes['source'])
                : 0,
        );
    }
}
