<?php

namespace Lunar\Storefront\Tests;

use Illuminate\Database\Eloquent\Model;
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

    protected function getEnvironmentSetUp($app): void
    {
        Model::preventLazyLoading();

        // File-backed SQLite so Testbench's RefreshDatabase cache survives:
        // `:memory:` forces a full migrate every test, which also re-runs the
        // published vendor migrations and collides with Lunar's schema.
        $dbPath = sys_get_temp_dir().'/lunar-storefront-test-'.getmypid().'.sqlite';
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        $app['config']->set('database.connections.testing.database', $dbPath);
        $app['config']->set('lunar.urls.generator', null);
    }

    // Register Laravel's default migrations on the migrator instead of running
    // them separately — a standalone migrate commits DDL and resets the
    // RefreshDatabase per-process cache. Package schema loads via providers.
    protected function defineDatabaseMigrations(): void
    {
        after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(default_migration_path());
        });
    }
}
