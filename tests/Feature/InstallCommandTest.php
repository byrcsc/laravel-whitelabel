<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function publishedConfig(): string
{
    return (string) config_path('whitelabel.php');
}

/**
 * @return list<string>
 */
function publishedMigrations(): array
{
    $found = File::glob((string) database_path('migrations/*_create_whitelabel_brands_table.php'));

    return array_values(array_filter($found, is_string(...)));
}

function removePublishedFiles(): void
{
    File::delete(publishedConfig());
    File::delete(publishedMigrations());
}

function migrations(): int
{
    return count(publishedMigrations());
}

/**
 * @param  array<string, bool>  $options
 */
function install(array $options = []): int
{
    // Never interactive: the command prompts about the database driver, and a
    // prompt in a test run is a hang.
    return Artisan::call('whitelabel:install', $options + ['--no-interaction' => true]);
}

beforeEach(function (): void {
    removePublishedFiles();
});

afterEach(function (): void {
    removePublishedFiles();
});

it('publishes a working config file', function (): void {
    expect(install())->toBe(Command::SUCCESS)
        ->and(File::exists(publishedConfig()))->toBeTrue();

    $published = require publishedConfig();

    expect($published)->toBeArray()
        ->toHaveKeys(['driver', 'default', 'brands', 'resolvers', 'mail', 'assets', 'css', 'cache']);
});

it('says what it did', function (): void {
    install();

    expect(Artisan::output())
        ->toContain('config/whitelabel.php')
        ->toContain('published')
        ->toContain('Whitelabel is installed');
});

it('changes nothing on a second run', function (): void {
    install();

    File::append(publishedConfig(), "\n// edited by hand\n");
    $edited = (string) File::get(publishedConfig());

    expect(install())->toBe(Command::SUCCESS)
        ->and(File::get(publishedConfig()))->toBe($edited);

    expect(Artisan::output())->toContain('already there');
});

it('overwrites when forced', function (): void {
    install();

    File::append(publishedConfig(), "\n// edited by hand\n");

    install(['--force' => true]);

    expect(File::get(publishedConfig()))->not->toContain('edited by hand');
});

it('leaves the migration alone when nobody asks for the database driver', function (): void {
    install();

    expect(migrations())->toBe(0);
    expect(Artisan::output())->toContain('skipped');
});

it('publishes the migration when asked for the database driver', function (): void {
    expect(install(['--database' => true]))->toBe(Command::SUCCESS)
        ->and(migrations())->toBe(1);

    expect(Artisan::output())->toContain('php artisan migrate');
});

it('publishes the migration once, however often it is asked for', function (): void {
    Artisan::call('vendor:publish', ['--tag' => 'whitelabel-migrations']);

    expect(migrations())->toBe(1);

    Artisan::call('vendor:publish', ['--tag' => 'whitelabel-migrations']);
    install();

    expect(migrations())->toBe(1);
    expect(Artisan::output())->toContain('already there');
});

it('republishes the migration when forced', function (): void {
    install(['--database' => true]);

    $migration = publishedMigrations()[0];
    File::append($migration, "\n// edited by hand\n");

    install(['--database' => true, '--force' => true]);

    expect(migrations())->toBe(1)
        ->and(File::get($migration))->not->toContain('edited by hand');
});

it('registers exactly the two documented commands', function (): void {
    $registered = [];

    foreach (Artisan::all() as $name => $command) {
        if (is_object($command) && str_starts_with($command::class, 'Byrcsc\\Whitelabel\\')) {
            $registered[] = $name;
        }
    }

    sort($registered);

    expect($registered)->toBe(['whitelabel:clear', 'whitelabel:install']);
});
