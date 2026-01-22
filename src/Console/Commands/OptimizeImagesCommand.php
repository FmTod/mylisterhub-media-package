<?php

namespace MyListerHub\Media\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use MyListerHub\Media\Jobs\OptimizeImageJob;
use MyListerHub\Media\Models\Image;

class OptimizeImagesCommand extends Command
{
    protected $signature = 'media:optimize-images {--chunk-size=500 : Chunk size for processing images}';

    protected $description = 'Optimize all local images';

    public function handle(): void
    {
        $this->info('Starting image optimization...');

        $batch = Bus::batch([])
            ->name('Optimize Images')
            ->dispatch();

        $count = 0;

        $chunkSize = (int) $this->option('chunk-size') ?: 500;

        Image::query()
            ->select(['id', 'source'])
            ->where('source', 'not like', 'http%')
            ->chunkById($chunkSize, function ($images) use ($batch, &$count) {
                $jobs = [];

                foreach ($images as $image) {
                    $jobs[] = new OptimizeImageJob($image);
                }

                $batch->add($jobs);
                $count += count($jobs);

                $this->info("Added " . count($jobs) . " jobs to batch {$batch->id}...");
            });

        if ($count === 0) {
            $this->info('No images to optimize.');
            return;
        }

        $this->info("Dispatched batch {$batch->id} with total {$count} jobs.");
    }
}
