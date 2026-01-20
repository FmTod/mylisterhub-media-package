<?php

namespace MyListerHub\Media\DataObjects;

readonly class ProcessedImage
{
    public function __construct(
        public string $path,
        public string $name,
        public int    $width,
        public int    $height,
        public int    $originalWidth,
        public int    $originalHeight,
    ) {}
}
