<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests;

use Byrcsc\Whitelabel\WhitelabelServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            WhitelabelServiceProvider::class,
        ];
    }

    /**
     * The package does not run its own migrations; applications opt in by
     * publishing them. The suite loads the shipped file directly.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            dirname(__DIR__).'/database/migrations/create_whitelabel_brands_table.php'
        );
    }
}
