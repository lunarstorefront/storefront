<?php

namespace Lunar\Storefront\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Staff;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

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

        $presets = config('storefront.presets', []);
        $name = (string) $this->option('preset');

        if (! isset($presets[$name])) {
            $this->components->error(sprintf('Unknown preset "%s". Available: %s', $name, implode(', ', array_keys($presets))));

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

        $this->components->info("Seeding the {$name} preset");
        app($presets[$name])->seed();

        if ($this->createAdmin() !== self::SUCCESS) {
            $this->components->error('Admin creation failed');

            return self::FAILURE;
        }

        if (! $this->option('skip-search')) {
            $this->components->info('Building the search index');

            if ($this->call(config('storefront.install.search_command', 'lunar:search:index')) !== self::SUCCESS) {
                $this->components->error('Search indexing failed');

                return self::FAILURE;
            }
        }

        if (! file_exists(public_path('storage'))) {
            $this->components->info('Linking storage');
            $this->call('storage:link');
        }

        $this->components->info('Storefront installed.');

        return self::SUCCESS;
    }

    private function createAdmin(): int
    {
        $values = [];
        foreach (['email', 'firstname', 'lastname', 'password'] as $key) {
            $value = $this->option("admin-{$key}") ?: config("storefront.install_admin.{$key}");
            $values[$key] = is_string($value) && $value !== '' ? $value : null;
        }

        /** @var class-string<Model> $model */
        $model = config('lunar.staff.model', Staff::class);

        if ($values['email'] !== null && $model::query()->where('email', $values['email'])->exists()) {
            $this->components->info("Admin {$values['email']} already exists");

            return self::SUCCESS;
        }

        if (in_array(null, $values, true)) {
            if (! $this->input->isInteractive()) {
                $this->components->warn('Skipping admin: pass --admin-email, --admin-firstname, --admin-lastname and --admin-password');

                return self::SUCCESS;
            }

            $values['email'] ??= text('Admin email', required: true);
            if ($model::query()->where('email', $values['email'])->exists()) {
                $this->components->info("Admin {$values['email']} already exists");

                return self::SUCCESS;
            }
            $values['firstname'] ??= text('Admin first name', required: true);
            $values['lastname'] ??= text('Admin last name', required: true);
            $values['password'] ??= password('Admin password', required: true);
        }

        return $this->call('lunar:create-admin', [
            '--email' => $values['email'],
            '--firstname' => $values['firstname'],
            '--lastname' => $values['lastname'],
            '--password' => $values['password'],
        ]);
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
