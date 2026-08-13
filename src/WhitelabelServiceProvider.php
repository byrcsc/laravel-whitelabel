<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

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
}
