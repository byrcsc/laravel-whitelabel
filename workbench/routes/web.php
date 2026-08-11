<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Facades\Whitelabel;
use Byrcsc\Whitelabel\Http\Middleware\EagerResolveBrand;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\Tenant;
use Workbench\App\Notifications\WelcomeNotification;

// The brand resolves lazily on this one, from the request host.
Route::get('/', fn () => view('demo'))->name('home');

// The same page, but resolved up front by the middleware and shared with the
// view as $brand.
Route::middleware(EagerResolveBrand::class)
    ->get('/eager', fn () => view('demo'))
    ->name('eager');

// An explicit override, which beats the host.
Route::get('/as/{brand}', function (string $brand) {
    Whitelabel::activate($brand);

    return view('demo');
})->name('as');

// A tenant becoming current, which activates its brand through the switch task.
Route::get('/tenant/{slug}', function (string $slug) {
    Tenant::query()->where('slug', $slug)->firstOrFail()->makeCurrent();

    return view('demo');
})->name('tenant');

// Every brand the configured driver knows, plus the ones in the table.
Route::get('/brands', function (BrandRepository $brands) {
    return view('demo-brands', ['brands' => $brands->all()]);
})->name('brands');

// Queue a branded notification, then run `php artisan queue:work --once`.
Route::get('/notify', function () {
    Notification::route('mail', 'someone@example.test')->notify(new WelcomeNotification);

    return view('demo-queued', ['brand' => Whitelabel::current()]);
})->name('notify');
