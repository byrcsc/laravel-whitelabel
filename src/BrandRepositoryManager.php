<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\CachedBrandRepository;
use Byrcsc\Whitelabel\Drivers\ConfigBrandRepository;
use Byrcsc\Whitelabel\Drivers\DatabaseBrandRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
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

    protected function createDatabaseDriver(): BrandRepository
    {
        return new DatabaseBrandRepository($this->config, $this->container->make(Dispatcher::class));
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver): BrandRepository
    {
        /** @var BrandRepository $repository */
        $repository = parent::createDriver($driver);

        return $this->shouldCache($driver)
            ? new CachedBrandRepository($repository, $this->container->make(CacheFactory::class), $this->config)
            : $repository;
    }

    /**
     * The config driver is never wrapped: its brands come from a PHP file that
     * Laravel's own config cache already makes free to read, and a second
     * cache in front of it would only add a way to serve a stale one.
     */
    private function shouldCache(string $driver): bool
    {
        if ($driver === ConfigBrandRepository::NAME) {
            return false;
        }

        return $this->config->get('whitelabel.cache.enabled', true) !== false;
    }
}
