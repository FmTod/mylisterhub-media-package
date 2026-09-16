<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutMiddleware();

    Http::preventStrayRequests();
});

it('rejects urls that do not reach a public http host', function (string $url) {
    get(route('media.images.proxy', ['url' => $url]))->assertStatus(400);

    Http::assertNothingSent();
})->with([
    'local file' => 'file:///etc/passwd',
    'php stream wrapper' => 'php://filter/resource=/etc/passwd',
    'loopback' => 'http://127.0.0.1/image.jpg',
    'private range' => 'http://10.0.0.5/image.jpg',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'carrier-grade nat' => 'http://100.64.0.1/image.jpg',
    'ipv6 loopback' => 'http://[::1]/image.jpg',
    'ipv4-mapped ipv6' => 'http://[::ffff:127.0.0.1]/image.jpg',
    'unresolvable host' => 'https://does-not-exist.invalid/image.jpg',
]);

it('streams an image from a public host', function () {
    Http::fake(['93.184.216.34/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

    $response = get(route('media.images.proxy', ['url' => 'http://93.184.216.34/image.jpg']));

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->streamedContent())->toBe('image-bytes');
});

it('does not follow redirects to an unvalidated location', function () {
    Http::fake(['93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

    get(route('media.images.proxy', ['url' => 'http://93.184.216.34/image.jpg']))->assertStatus(502);

    Http::assertSentCount(1);
});
