<?php

namespace MyListerHub\Media\DataMappers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ImageMapper
{
    public static function fromUrls(iterable $images): array
    {
        return array_map(static fn (string $url) => [
            'source' => $url,
            'name' => strtok(basename($url), '?'),
        ], Arr::fromArrayable($images));
    }

    public static function toUrls(Collection|array $images, bool $bustCache = false): array
    {
        return collect($images)
            ->sortBy('order')
            ->pluck('url')
            ->map(fn (string $url) => str_replace(' ', '%20', $url) . ($bustCache ? '?' . now()->getTimestamp() : ''))
            ->toArray();
    }
}
