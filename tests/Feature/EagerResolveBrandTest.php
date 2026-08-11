<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Http\Middleware\EagerResolveBrand;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => ['name' => 'Default'],
        'globex' => ['name' => 'Globex', 'domain' => 'app.globex.com'],
    ]);
});

function reportsResolution(): string
{
    return app(Whitelabel::class)->isResolved() ? 'resolved' : 'lazy';
}

it('resolves the brand before the route runs', function (): void {
    Route::middleware(EagerResolveBrand::class)->get('/eager', reportsResolution(...));

    get('http://app.globex.com/eager')->assertSee('resolved');
});

it('shares the brand with every view', function (): void {
    Route::middleware(EagerResolveBrand::class)->get('/shared', function (): string {
        $brand = view()->shared('brand');

        return $brand instanceof Brand ? (string) $brand->name() : 'none';
    });

    get('http://app.globex.com/shared')->assertSee('Globex');
});

it('leaves resolution lazy without the middleware', function (): void {
    Route::get('/lazy', reportsResolution(...));

    get('http://app.globex.com/lazy')->assertSee('lazy');
});
