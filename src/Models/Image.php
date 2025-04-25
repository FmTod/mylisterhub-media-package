<?php

namespace MyListerHub\Media\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
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
