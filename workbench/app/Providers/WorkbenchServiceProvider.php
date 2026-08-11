<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Byrcsc\Whitelabel\Spatie\SwitchTenantBrandTask;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the demo application up so every seam of the package is reachable.
 *
 * The brands here are deliberately partial: Globex names one colour and no
 * logo, so the fallback to the default brand is visible on the page rather
 * than only in a test.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Set in boot, not register: the package merges its own shipped config
     * during registration, and the demo's brands have to survive that.
     */
    public function boot(): void
    {
        config([
            'whitelabel.driver' => 'config',
            'whitelabel.default' => 'default',
            'whitelabel.brands' => [
                'default' => [
                    'name' => 'Whitelabel Demo',
                    'colors' => ['primary' => '#1f2937', 'secondary' => '#6b7280'],
                    'logo' => ['disk' => 'public', 'path' => 'brands/default/logo.svg'],
                    'favicon' => ['disk' => 'public', 'path' => 'brands/default/favicon.svg'],
                    'mail' => ['from_name' => 'Whitelabel Demo', 'from_address' => 'hello@whitelabel.test'],
                    'settings' => ['support_url' => 'https://support.whitelabel.test'],
                ],
                'acme' => [
                    'name' => 'Acme',
                    'domain' => 'acme.localhost',
                    'colors' => ['primary' => '#7c3aed', 'secondary' => '#a78bfa'],
                    'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
                    'mail' => ['from_name' => 'Acme Support', 'from_address' => 'hello@acme.test'],
                    'settings' => ['support_url' => 'https://support.acme.test'],
                ],
                'globex' => [
                    // Nothing but a name, a domain, and one colour: everything
                    // else on the page arrives from the default brand.
                    'name' => 'Globex',
                    'domain' => 'globex.localhost',
                    'colors' => ['primary' => '#0ea5e9'],
                ],
            ],

            'multitenancy.switch_tenant_tasks' => [SwitchTenantBrandTask::class],
            'multitenancy.tenant_model' => \Workbench\App\Models\Tenant::class,
            'multitenancy.queues_are_tenant_aware_by_default' => false,

            // The demo serves brand assets straight out of public/, so no
            // storage symlink is needed and `composer build` can be re-run.
            'filesystems.disks.public' => [
                'driver' => 'local',
                'root' => public_path(),
                'url' => '/',
                'visibility' => 'public',
                'throw' => false,
            ],

            'mail.default' => 'log',
            'queue.default' => 'database',
        ]);

        $this->placeDemoAssets();
    }

    /**
     * Put the demo's logos where the public disk can serve them.
     *
     * The demo does this itself rather than through a build step, so
     * `composer build` stays re-runnable and the app needs no storage symlink.
     * Copied once: delete `public/brands` to pick up an edited SVG.
     */
    private function placeDemoAssets(): void
    {
        $files = new Filesystem;
        $target = public_path('brands');

        // Not during a test run: the suite fakes the public disk, so the copy
        // would only be writing into the Testbench skeleton for nobody.
        if ($this->app->runningUnitTests() || $files->isDirectory($target)) {
            return;
        }

        $files->ensureDirectoryExists($target);
        $files->copyDirectory(__DIR__.'/../../resources/assets/brands', $target);
    }
}
