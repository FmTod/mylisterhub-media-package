<?php

namespace MyListerHub\Media\DataMappers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use MyListerHub\Media\Models\Image;

class ImageMapper
{
    public static function fromUrls(iterable $images): array
    {
        return array_map(static fn (string $url) => [
            'source' => $url,
            'name' => strtok(basename($url), '?'),
        ], Arr::fromArrayable($images));
    }

    public static function toUrls(Collection|array $images, bool $bustCache = false, array $data = []): array
    {
        return collect($images)
            ->sortBy('order')
            ->map(fn (Image $image) => $image->buildUrl($bustCache, $data))
            ->toArray();
    }
}
