<?php

namespace MyListerHub\Media\Jobs;

use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MyListerHub\Media\Facades\Media;
use MyListerHub\Media\Models\Image;

class OptimizeImageJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Image $image
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (Str::match('/^http(s)?:\/\//', $this->image->source)) {
            return;
        }

        $diskName = config('media.storage.images.disk', 'public');
        $path = config('media.storage.images.path', 'media/images');
        $relativePath = "{$path}/{$this->image->source}";

        $disk = Storage::disk($diskName);

        if (! $disk->exists($relativePath)) {
            return;
        }

        $extension = pathinfo($this->image->source, PATHINFO_EXTENSION) ?: 'jpg';
        $tempPath = sprintf('%s.%s', tempnam(sys_get_temp_dir(), 'media_optimize_'), $extension);

        try {
            $stream = $disk->readStream($relativePath);
            $tempStream = fopen($tempPath, 'wb');
            stream_copy_to_stream($stream, $tempStream);
            fclose($stream);
            fclose($tempStream);

            $result = Media::processImage($tempPath, $this->image->source, optimize: true);

            $storedResult = Media::storeImage($result, $result->name, $diskName);

            if ($storedResult->name !== $this->image->source) {
                $disk->delete($relativePath);
            }

            $this->image->update([
                'name' => $storedResult->name,
                'source' => $storedResult->name,
                'width' => $storedResult->width,
                'height' => $storedResult->height,
            ]);

            if (file_exists($result->path)) {
                @unlink($result->path);
            }

            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        } catch (Exception $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            throw $e;
        }
    }
}
