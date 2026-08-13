<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\ConfigBrandRepository;
use Illuminate\Support\Manager;

/**
 * Resolves the configured brand driver behind {@see BrandRepository}.
 *
 * This is the extension point for custom drivers:
 *
 * ```php
 * app(BrandRepositoryManager::class)->extend('redis', fn () => new RedisBrandRepository);
 * ```
 *
 * @method BrandRepository driver(string|null $driver = null)
 */
class BrandRepositoryManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('whitelabel.driver');

        return is_string($driver) && $driver !== '' ? $driver : ConfigBrandRepository::NAME;
    }

    protected function createConfigDriver(): BrandRepository
    {
        return new ConfigBrandRepository($this->config);
    }
}
