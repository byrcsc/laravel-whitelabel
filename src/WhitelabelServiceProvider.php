<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Commands\ClearBrandCacheCommand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WhitelabelServiceProvider extends PackageServiceProvider
{
    /**
     * Points at which the active brand must not survive into the next piece
     * of work.
     *
     * The Octane events are listened for by name: the class does not exist
     * unless Octane is installed, and an event that never fires costs nothing.
     *
     * @var list<string>
     */
    private const RESET_EVENTS = [
        'Illuminate\Queue\Events\JobProcessing',
        'Illuminate\Queue\Events\JobProcessed',
        'Illuminate\Queue\Events\JobFailed',
        'Laravel\Octane\Events\RequestReceived',
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskReceived',
        'Laravel\Octane\Events\TickReceived',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('whitelabel')
            ->hasConfigFile()
            ->hasMigration('create_whitelabel_brands_table')
            ->hasCommand(ClearBrandCacheCommand::class);
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

        $this->app->singleton(Whitelabel::class);
    }

    public function packageBooted(): void
    {
        $this->forgetBrandBetweenUnitsOfWork();
    }

    /**
     * The manager is a singleton, which in a worker or under Octane outlives
     * the request or job that resolved a brand. Without this, the next unit of
     * work would inherit whichever brand the last one happened to leave
     * behind.
     */
    private function forgetBrandBetweenUnitsOfWork(): void
    {
        $app = $this->app;

        $app->make(Dispatcher::class)->listen(self::RESET_EVENTS, static function () use ($app): void {
            if ($app->resolved(Whitelabel::class)) {
                $app->make(Whitelabel::class)->flush();
            }
        });
    }
}
