<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests;

use Byrcsc\Whitelabel\WhitelabelServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
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
}
