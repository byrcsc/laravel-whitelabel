<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WhitelabelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('whitelabel')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BrandRepositoryManager::class);

        // Bound rather than shared: the manager already caches the driver it
        // built, and going through it every time keeps a change of
        // whitelabel.driver from being ignored by an earlier resolution.
        $this->app->bind(
            BrandRepository::class,
            static fn (Application $app): BrandRepository => $app->make(BrandRepositoryManager::class)->driver(),
        );
    }
}
