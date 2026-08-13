<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\WhitelabelServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function (): void {
    expect(config('whitelabel'))->toBeArray();
});

it('publishes the config file', function (): void {
    $paths = array_keys(
        ServiceProvider::pathsToPublish(WhitelabelServiceProvider::class, 'whitelabel-config')
    );

    expect($paths)->toHaveCount(1)
        ->and($paths[0])->toEndWith('config/whitelabel.php');
});
