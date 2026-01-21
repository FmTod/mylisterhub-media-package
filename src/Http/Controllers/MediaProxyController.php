<?php

namespace MyListerHub\Media\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyController extends Controller
{
    /**
     * Proxy an external image to avoid CORS issues.
     * Streams the response to avoid memory issues with large files.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function proxy(Request $request): ?StreamedResponse
    {
        $url = $request->query('url');

        if (! $url) {
            abort(400, 'URL parameter is required');
        }

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            Log::error('Invalid URL provided to image proxy', ['url' => $url]);
            abort(400, 'Invalid URL');
        }

        try {
            // Create a stream resource from the URL
            // specific timeout to avoid hanging processes
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true, // Handle errors manually
                ]
            ]);

            $stream = @fopen($url, 'rb', false, $context);

            if ($stream === false) {
                Log::error('Failed to open stream for image proxy', ['url' => $url]);
                abort(404, 'Image not found or inaccessible');
            }

            // Get headers from the stream wrapper
            $meta = stream_get_meta_data($stream);
            $wrapperData = $meta['wrapper_data'] ?? [];

            // Extract content type and status code
            $contentType = 'application/octet-stream';
            $statusCode = 200;

            foreach ($wrapperData as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = trim(substr($header, 13));
                }
                if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int)$matches[1];
                }
            }

            if ($statusCode >= 400) {
                fclose($stream);
                Log::error('Remote server returned error', ['url' => $url, 'status' => $statusCode]);
                abort($statusCode, 'Remote server error');
            }

            // Return streamed response
            return response()->stream(
                function () use ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                },
                200,
                [
                    'Content-Type' => $contentType,
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'public, max-age=3600',
                ]
            );

        } catch (\Exception $e) {
            Log::error('Exception in image proxy', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Failed to proxy image: ' . $e->getMessage());
            return null;
        }
    }
}
