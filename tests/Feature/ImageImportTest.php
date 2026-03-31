<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use MyListerHub\Media\Models\Image;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake(config('media.storage.images.disk', 'public'));

    $fixtureDirectory = sys_get_temp_dir() . '/media-import-fixtures-' . bin2hex(random_bytes(8));
    mkdir($fixtureDirectory, 0777, true);

    $firstFixture = $fixtureDirectory . '/first.jpg';
    $secondFixture = $fixtureDirectory . '/second.jpg';
    $thirdFixture = $fixtureDirectory . '/third.jpg';

    createFixtureImage($firstFixture, 20, 10, [255, 0, 0]);
    createFixtureImage($secondFixture, 30, 15, [0, 255, 0]);
    createFixtureImage($thirdFixture, 40, 20, [0, 0, 255]);

    $routerPath = $fixtureDirectory . '/router.php';
    $routerScript = sprintf(<<<'PHP'
        <?php

        $variant = $_GET['variant'] ?? '1';

        $map = [
            '1' => %s,
            '2' => %s,
            '3' => %s,
        ];

        $file = $map[$variant] ?? $map['1'];

        header('Content-Type: image/jpeg');
        readfile($file);
        PHP,

        var_export($firstFixture, true),
        var_export($secondFixture, true),
        var_export($thirdFixture, true),
    );

    file_put_contents($routerPath, $routerScript);

    $port = random_int(41000, 45000);
    $command = sprintf('php -S 127.0.0.1:%d %s', $port, escapeshellarg($routerPath));
    $process = proc_open($command, [STDIN, ['pipe', 'w'], ['pipe', 'w']], $pipes, $fixtureDirectory);

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start temporary image server.');
    }

    waitForServer($port);

    $this->fixtureDirectory = $fixtureDirectory;
    $this->serverProcess = $process;
    $this->serverUrl = "http://127.0.0.1:{$port}";
});

afterEach(function () {
    if (isset($this->serverProcess) && is_resource($this->serverProcess)) {
        proc_terminate($this->serverProcess);
        proc_close($this->serverProcess);
    }

    if (! isset($this->fixtureDirectory) || ! is_dir($this->fixtureDirectory)) {
        return;
    }

    foreach (glob($this->fixtureDirectory . '/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->fixtureDirectory);
});

it('imports remote images with duplicate names into distinct stored files', function () {
    /** @var Collection<int, Image> $images */
    $images = collect([
        $this->serverUrl . '/s-l500.jpg?variant=1',
        $this->serverUrl . '/s-l500.jpg?variant=2',
        $this->serverUrl . '/s-l500.jpg?variant=3',
    ])->map(fn (string $source) => Image::factory()->create([
        'name' => 's-l500.jpg',
        'source' => $source,
        'width' => null,
        'height' => null,
    ]));

    expect($images->pluck('name')->unique()->count())->toBe(1);

    $this->withoutMiddleware();

    postJson(route('media.images.batch.store-file'), [
        'images' => $images->pluck('id')->all(),
    ])->assertSuccessful();

    $imported = Image::query()
        ->whereIn('id', $images->pluck('id')->all())
        ->orderBy('id')
        ->get();

    expect($imported)->toHaveCount(3)
        ->and($imported->pluck('source')->unique()->count())->toBe(3)
        ->and($imported->every(fn (Image $image) => ! str_starts_with($image->source, 'http')))->toBeTrue();
});

function createFixtureImage(string $path, int $width, int $height, array $rgb): void
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);

    imagefill($image, 0, 0, $background);
    imagejpeg($image, $path, 90);
    imagedestroy($image);
}

function waitForServer(int $port): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port);

        if (is_resource($connection)) {
            fclose($connection);

            return;
        }

        usleep(20_000);
    }

    throw new RuntimeException('Temporary image server did not start in time.');
}
