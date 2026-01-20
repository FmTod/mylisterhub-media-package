<?php

namespace MyListerHub\Media\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use MyListerHub\Media\MediaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'MyListerHub\\Media\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        foreach (glob(__DIR__ . '/../database/migrations/*.php.stub') as $filename) {
            $migration = include $filename;
            $migration->up();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            MediaServiceProvider::class,
        ];
    }
}
