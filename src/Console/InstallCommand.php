<?php

namespace Lunar\Storefront\Console;

use Illuminate\Console\Command;
use Lunar\Core\Models\Country;
use Throwable;

class InstallCommand extends Command
{
    protected $signature = 'storefront:install
        {--preset=uk : The preset to seed}
        {--skip-search : Do not build the search index}
        {--admin-email= : Email for the admin user}
        {--admin-firstname= : First name for the admin user}
        {--admin-lastname= : Last name for the admin user}
        {--admin-password= : Password for the admin user}';

    protected $description = 'Install and seed the storefront';

    public function handle(): int
    {
        $missing = collect(config('storefront.install.requires', []))
            ->reject(fn (string $class) => class_exists($class))
            ->keys();

        if ($missing->isNotEmpty()) {
            $this->components->error('Missing required packages: '.$missing->implode(', '));

            return self::FAILURE;
        }

        $this->components->info('Publishing config and assets');
        foreach (config('storefront.install.publish.config', []) as $tag) {
            $this->publish($tag);
        }
        foreach (config('storefront.install.publish.assets', []) as $tag) {
            $this->publish($tag, force: true);
        }

        $this->components->info('Running migrations');
        $this->call('migrate', ['--force' => true]);

        if (! $this->importCountries()) {
            $this->components->error('Country import failed');

            return self::FAILURE;
        }

        $presets = config('storefront.presets', []);
        $name = (string) $this->option('preset');

        if (! isset($presets[$name])) {
            $this->components->error(sprintf('Unknown preset "%s". Available: %s', $name, implode(', ', array_keys($presets))));

            return self::FAILURE;
        }

        $this->components->info("Seeding the {$name} preset");
        app($presets[$name])->seed();

        return self::SUCCESS;
    }

    private function publish(string $tag, bool $force = false): void
    {
        try {
            $this->callSilently('vendor:publish', ['--tag' => $tag, '--force' => $force]);
        } catch (Throwable $e) {
            $this->components->warn("Could not publish \"{$tag}\": {$e->getMessage()}");
        }
    }

    private function importCountries(): bool
    {
        if (Country::query()->exists()) {
            return true;
        }

        $this->components->info('Importing countries');

        try {
            $this->call('lunar:import:address-data');
        } catch (Throwable $e) {
            $this->components->warn($e->getMessage());

            return false;
        }

        return Country::query()->exists();
    }
}
