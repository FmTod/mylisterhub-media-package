<?php

namespace MyListerHub\Media\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyController extends Controller
{
    /**
     * Proxy an external image to avoid CORS issues.
     * Streams the response to avoid memory issues with large files.
     */
    public function proxy(Request $request): StreamedResponse
    {
        $url = $request->string('url')->toString();

        if ($url === '') {
            abort(400, 'URL parameter is required');
        }

        $target = $this->resolvePublicTarget($url);

        if ($target === null) {
            Log::warning('Rejected image proxy URL', ['url' => $url]);
            abort(400, 'Invalid URL');
        }

        try {
            $response = Http::withOptions([
                'stream' => true,
                'timeout' => 30,
                // A redirect could point at an internal address that was never validated.
                'allow_redirects' => false,
                // Connect to the address validated above; a second DNS lookup could return a private one.
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
            ])->get($url);
        } catch (ConnectionException $exception) {
            Log::error('Failed to open stream for image proxy', ['url' => $url, 'error' => $exception->getMessage()]);
            abort(404, 'Image not found or inaccessible');
        }

        if (! $response->successful()) {
            Log::error('Remote server returned error', ['url' => $url, 'status' => $response->status()]);
            abort($response->status() >= 400 ? $response->status() : 502, 'Remote server error');
        }

        $body = $response->toPsrResponse()->getBody();

        return response()->stream(
            function () use ($body) {
                while (! $body->eof()) {
                    echo $body->read(8192);
                }
            },
            200,
            [
                'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    /**
     * Accept only http(s) URLs whose host resolves exclusively to public addresses.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    protected function resolvePublicTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $hostname = trim($host, '[]');

        $addresses = filter_var($hostname, FILTER_VALIDATE_IP) !== false
            ? [$hostname]
            : array_map(
                static fn (array $record): string => $record['ip'] ?? $record['ipv6'],
                // Unresolvable hosts raise a warning, which Laravel would turn into a 500.
                @dns_get_record($hostname, DNS_A | DNS_AAAA) ?: [],
            );

        // Every record must be public: a host answering with one public and one private address is not.
        if ($addresses === [] || array_filter($addresses, fn (string $ip): bool => ! $this->isPublicAddress($ip)) !== []) {
            return null;
        }

        $ip = $addresses[0];

        return [
            'host' => $hostname,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ip' => str_contains($ip, ':') ? "[{$ip}]" : $ip,
        ];
    }

    protected function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // 100.64.0.0/10 (carrier-grade NAT) passes the flags above but is routinely used for cluster-internal addressing.
        return ! (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && (ip2long($ip) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000));
    }
}
