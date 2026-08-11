<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests;

use Byrcsc\Whitelabel\WhitelabelServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
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
    /**
     * Views the suite renders, under their own namespace.
     *
     * Registered here rather than in `defineEnvironment`, which runs before
     * the package boots: resolving the view factory that early leaves the
     * finder holding paths the package has not added its own to yet.
     */
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('whitelabel-tests', __DIR__.'/resources/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            dirname(__DIR__).'/database/migrations/create_whitelabel_brands_table.php'
        );
    }
}
