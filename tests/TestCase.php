<?php

namespace Lunar\Storefront\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Lunar\Core\LunarServiceProvider;
use Lunar\Nestedset\NestedSetServiceProvider;
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

        // Build a clean schema per test from the migrator's registered paths
        // (core + Laravel defaults). RefreshDatabase's setup-time migrate
        // collides with Lunar core's consolidated schema under Testbench, so we
        // migrate explicitly here where the migrator paths are fully resolved.
        Artisan::call('migrate:fresh', ['--database' => 'testing']);

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
        // Fixed key (not random per test) so Crypt-based encrypt/decrypt round
        // trips stay consistent across the encrypter singleton's lifecycle.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
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
