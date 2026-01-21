<?php

namespace MyListerHub\Media\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class ConfigureS3Cors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:configure-s3-cors
                            {--disk= : The disk to configure (defaults to media disk)}
                            {--domain= : The allowed domain (e.g. https://example.com). Defaults to app.url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure CORS for the S3 bucket to allow browser access';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = $this->option('disk') ?: config('media.storage.images.disk', 's3');
        $rawUrl = $this->option('domain') ?: config('app.url');

        $allowedOrigin = $rawUrl;

        if ($rawUrl !== '*') {
            $parsed = parse_url($rawUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? $rawUrl;
            $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';
            $allowedOrigin = "{$scheme}://{$host}{$port}";
        }

        $this->info("Configuring CORS for disk: {$diskName}");
        $this->info("Allowed Origin: {$allowedOrigin}");

        try {
            $disk = Storage::disk($diskName);

            // Verify it's an S3 driver
            $config = config("filesystems.disks.{$diskName}");
            if (($config['driver'] ?? '') !== 's3') {
                $this->error("Disk '{$diskName}' is not using the 's3' driver.");

                return self::FAILURE;
            }

            if (! $disk instanceof FilesystemAdapter) {
                $this->error("Disk '{$diskName}' is not a FilesystemAdapter instance.");

                return self::FAILURE;
            }

            // Get the S3 Client
            /** @var \Aws\S3\S3Client $client */
            $client = $disk->getClient();
            $bucket = $config['bucket'];

            $corsRules = [
                [
                    'AllowedHeaders' => ['*'],
                    'AllowedMethods' => ['GET', 'HEAD'],
                    'AllowedOrigins' => [$allowedOrigin],
                    'ExposeHeaders' => [
                        'ETag',
                        'Access-Control-Allow-Origin',
                        'Content-Length',
                        'Content-Type',
                        'Last-Modified',
                        'Cache-Control',
                    ],
                    'MaxAgeSeconds' => 3000,
                ],
            ];

            $this->info("Applying CORS policy to bucket: {$bucket}...");

            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => [
                    'CORSRules' => $corsRules,
                ],
            ]);

            $this->info('CORS configuration updated successfully!');

            // Verify
            $result = $client->getBucketCors(['Bucket' => $bucket]);
            $this->line('Current CORS Configuration:');
            $this->line(json_encode($result->get('CORSRules'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (S3Exception $e) {
            $this->error('AWS Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
