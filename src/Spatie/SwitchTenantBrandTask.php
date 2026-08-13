<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Spatie;

use Byrcsc\Whitelabel\Contracts\ProvidesBrand;
use Byrcsc\Whitelabel\Whitelabel;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Activates a tenant's brand whenever that tenant becomes current.
 *
 * Register it in `config/multitenancy.php`:
 *
 * ```php
 * 'switch_tenant_tasks' => [
 *     Byrcsc\Whitelabel\Spatie\SwitchTenantBrandTask::class,
 * ],
 * ```
 *
 * Spatie re-runs switch tasks inside tenant-aware queued jobs, so this one is
 * written to be idempotent: making the same tenant current twice leaves the
 * same brand active and fires no second activation.
 *
 * A tenant that does not implement {@see ProvidesBrand} is ignored, and a
 * tenant that implements it but returns null leaves the resolver chain to
 * carry on to the request domain and the configured default.
 */
final class SwitchTenantBrandTask implements SwitchTenantTask
{
    /**
     * The brand this task activated, so it only takes back its own.
     */
    private ?string $activated = null;

    public function __construct(private readonly Whitelabel $whitelabel) {}

    public function makeCurrent(IsTenant $tenant): void
    {
        if (! $tenant instanceof ProvidesBrand) {
            return;
        }

        $brand = $tenant->brand();

        if ($brand === null) {
            return;
        }

        $this->activated = $brand->id();

        $this->whitelabel->activate($brand);
    }

    /**
     * Put the brand back the way it was, if it is still this task's to put back.
     *
     * A brand activated by hand outlives the tenant that happened to be
     * current at the time. Forgetting unconditionally would make
     * `Tenant::forgetCurrent()`, which Spatie calls after every tenant-aware
     * job, quietly undo an explicit `Whitelabel::activate()`.
     */
    public function forgetCurrent(): void
    {
        $activated = $this->activated;

        $this->activated = null;

        if ($activated !== null && $this->whitelabel->current()?->id() === $activated) {
            $this->whitelabel->forget();
        }
    }
}
