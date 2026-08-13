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
     * Spatie's provider is named as a string and only registered when it is
     * installed, so the no-Spatie CI job boots the same test case unchanged.
     */
    private const MULTITENANCY_PROVIDER = 'Spatie\Multitenancy\MultitenancyServiceProvider';

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        if (class_exists(self::MULTITENANCY_PROVIDER)) {
            return [WhitelabelServiceProvider::class, self::MULTITENANCY_PROVIDER];
        }

        return [WhitelabelServiceProvider::class];
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
