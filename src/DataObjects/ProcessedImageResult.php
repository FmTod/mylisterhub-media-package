<?php

namespace MyListerHub\Media\DataObjects;

readonly class ProcessedImageResult
{
    public function __construct(
        public int $width,
        public int $height,
        public string $path,
        public string $name,
    ) {}
}
