<?php

namespace Lunar\Storefront\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\Catalog\CatalogServiceProvider;
use Lunar\Kernel\KernelServiceProvider;
use Lunar\Sales\SalesServiceProvider;
use Lunar\Storefront\StorefrontServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            NestedSetServiceProvider::class,
            ActivitylogServiceProvider::class,
            MediaLibraryServiceProvider::class,
            LaravelDataServiceProvider::class,
            KernelServiceProvider::class,
            CatalogServiceProvider::class,
            SalesServiceProvider::class,
            StorefrontServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('lunar.kernel.database.connection', 'testing');
        $app['config']->set('lunar.kernel.urls.generator', null);
        $app['config']->set('activitylog.database_connection', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $this->artisan('vendor:publish', [
            '--provider' => ActivitylogServiceProvider::class,
            '--tag' => 'activitylog-migrations',
        ])->run();

        $this->artisan('vendor:publish', [
            '--provider' => MediaLibraryServiceProvider::class,
            '--tag' => 'medialibrary-migrations',
        ])->run();

        $this->loadMigrationsFrom($this->app->databasePath('migrations'));
    }
}
