<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Commands\ClearBrandCacheCommand;
use Byrcsc\Whitelabel\Commands\InstallCommand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Mail\BrandedMailViews;
use Byrcsc\Whitelabel\Mail\OverrideMailSender;
use Byrcsc\Whitelabel\Queue\BrandContext;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WhitelabelServiceProvider extends PackageServiceProvider
{
    /**
     * Points at which the active brand must not survive into the next piece
     * of work.
     *
     * Every one of these fires *after* a unit of work, or before one has begun
     * to set itself up. Nothing here fires at the start of a job, because
     * Spatie's tenant switch also hooks `JobProcessing` and there is no
     * ordering between two listeners on the same event: resetting there would
     * be a coin flip on whether the tenant's brand survived to the job body.
     *
     * The Octane events are listened for by name: the class does not exist
     * unless Octane is installed, and an event that never fires costs nothing.
     *
     * @var list<string>
     */
    private const RESET_EVENTS = [
        'Illuminate\Queue\Events\Looping',
        'Illuminate\Queue\Events\JobProcessed',
        'Illuminate\Queue\Events\JobFailed',
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskTerminated',
        'Laravel\Octane\Events\TickTerminated',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('whitelabel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_whitelabel_brands_table')
            ->hasCommands(InstallCommand::class, ClearBrandCacheCommand::class);
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
        // Registered as a namespace rather than one component at a time, so
        // the tags read <x-whitelabel::logo /> and a published view under
        // resources/views/vendor/whitelabel overrides the shipped one.
        Blade::componentNamespace('Byrcsc\\Whitelabel\\View\\Components', 'whitelabel');

        $this->forgetBrandBetweenUnitsOfWork();
        $this->carryBrandIntoQueuedWork();
        $this->brandOutgoingMail();
    }

    /**
     * Put the brand into markdown mail, and optionally onto the envelope.
     */
    private function brandOutgoingMail(): void
    {
        $views = new BrandedMailViews($this->app->make(ConfigRepository::class));
        $path = __DIR__.'/../resources/views/mail';

        // callAfterResolving, not a config append: Markdown is a singleton that
        // reads mail.markdown.paths once, in its constructor, so anything that
        // resolved it before this package booted would never see the branding.
        $this->callAfterResolving(
            Markdown::class,
            static fn (Markdown $markdown) => $views->applyTo($markdown, $path),
        );

        $this->app->make(Dispatcher::class)->listen(MessageSending::class, OverrideMailSender::class);
    }

    /**
     * Stamp outgoing jobs with the active brand, and give it back to the jobs
     * that asked for it with the `BrandAware` trait.
     */
    private function carryBrandIntoQueuedWork(): void
    {
        $context = new BrandContext($this->app);

        $context->capture();

        $this->app->make(Dispatcher::class)->listen(
            JobProcessing::class,
            static fn (JobProcessing $event) => $context->restore($event),
        );
    }

    /**
     * The manager is a singleton, which in a worker or under Octane outlives
     * the request or job that resolved a brand. Without this, the next unit of
     * work would inherit whichever brand the last one happened to leave
     * behind.
     *
     * Clearing after each unit of work rather than before the next one is what
     * keeps this out of the way of anything that activates a brand as a job
     * starts, Spatie's tenant switch included.
     */
    private function forgetBrandBetweenUnitsOfWork(): void
    {
        $app = $this->app;

        $app->make(Dispatcher::class)->listen(self::RESET_EVENTS, static function (object $event) use ($app): void {
            // A sync job runs inside whatever dispatched it, so its completion
            // is not the end of a unit of work: clearing here would take the
            // brand out from under the request that is still running.
            if (property_exists($event, 'job') && $event->job instanceof SyncJob) {
                return;
            }

            if ($app->resolved(Whitelabel::class)) {
                $app->make(Whitelabel::class)->flush();
            }
        });
    }
}
