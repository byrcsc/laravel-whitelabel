<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'whitelabel:install
        {--database : Publish the brands migration, for the database driver}
        {--force : Overwrite files that are already there}';

    protected $description = 'Publish the Whitelabel config, and the brands migration if you want the database driver';

    public function handle(Filesystem $files): int
    {
        $published = $this->publishConfig($files) && $this->publishMigration($files);

        $this->newLine();

        if (! $published) {
            $this->components->error('Whitelabel could not publish everything it needed to.');

            return self::FAILURE;
        }

        $this->components->info('Whitelabel is installed. Define your brands in config/whitelabel.php.');

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $files): bool
    {
        $target = $this->laravel->configPath('whitelabel.php');

        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail('config/whitelabel.php', '<fg=yellow>already there</>');

            return true;
        }

        $this->publish('whitelabel-config');

        return $this->report('config/whitelabel.php', $files->exists($target));
    }

    /**
     * Offer the migration, which only the database driver needs.
     *
     * Publishing is idempotent on its own: the publisher reuses the timestamped
     * filename it finds rather than adding a second migration for the same
     * table. The check here is only so a second install does not ask again.
     */
    private function publishMigration(Filesystem $files): bool
    {
        if ($this->migrationExists($files) && ! $this->option('force')) {
            $this->components->twoColumnDetail('brands migration', '<fg=yellow>already there</>');

            return true;
        }

        if (! $this->wantsTheDatabaseDriver()) {
            $this->components->twoColumnDetail('brands migration', '<fg=gray>skipped</>');

            return true;
        }

        $this->publish('whitelabel-migrations');

        if (! $this->report('brands migration', $this->migrationExists($files))) {
            return false;
        }

        $this->components->warn('Run "php artisan migrate", and set whitelabel.driver to "database".');

        return true;
    }

    /**
     * Ask, unless the caller already said, or there is nobody to ask.
     *
     * `--database` is what makes this scriptable: the command has to be usable
     * from a deploy step or another installer, where a prompt would hang.
     */
    private function wantsTheDatabaseDriver(): bool
    {
        if ($this->option('database') === true) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm(
            label: 'Store brands in the database?',
            default: false,
            hint: 'Say no to keep them in config/whitelabel.php, which is the default.',
        );
    }

    private function migrationExists(Filesystem $files): bool
    {
        return $files->glob($this->laravel->databasePath('migrations/*_create_whitelabel_brands_table.php')) !== [];
    }

    private function publish(string $tag): void
    {
        $this->callSilently('vendor:publish', array_filter([
            '--tag' => $tag,
            '--force' => $this->option('force') ? true : null,
        ]));
    }

    /**
     * Say what happened, and answer whether it worked.
     *
     * `vendor:publish` reports its own failures to an output this command
     * silenced, and returns nothing either way, so the only honest check is
     * whether the file is now there.
     */
    private function report(string $label, bool $succeeded): bool
    {
        $this->components->twoColumnDetail(
            $label,
            $succeeded ? '<fg=green>published</>' : '<fg=red>failed</>',
        );

        return $succeeded;
    }
}
