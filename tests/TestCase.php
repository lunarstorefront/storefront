<?php

namespace Lunar\Storefront\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\Core\LunarServiceProvider;
use Lunar\Search\SearchServiceProvider;
use Lunar\Storefront\StorefrontServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

use function Orchestra\Testbench\after_resolving;
use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        activity()->disableLogging();
        $this->freezeTime();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            NestedSetServiceProvider::class,
            ActivitylogServiceProvider::class,
            MediaLibraryServiceProvider::class,
            BlinkServiceProvider::class,
            PermissionServiceProvider::class,
            LunarServiceProvider::class,
            SearchServiceProvider::class,
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

        $app['config']->set('lunar.database.connection', 'testing');
        $app['config']->set('lunar.urls.generator', null);
        $app['config']->set('activitylog.database_connection', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    // Register Laravel's default migrations on the migrator alongside the
    // package migrations so all migrations share one global ordering. Running
    // them in a separate pass breaks ordering (and RefreshDatabase's cache).
    protected function defineDatabaseMigrations(): void
    {
        after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(default_migration_path());
        });
    }
}
