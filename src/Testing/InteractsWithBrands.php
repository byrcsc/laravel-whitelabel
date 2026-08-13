<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Testing;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Whitelabel;
use PHPUnit\Framework\Attributes\Before;

/**
 * Brand helpers for your own test suite.
 *
 * ```php
 * use Byrcsc\Whitelabel\Testing\InteractsWithBrands;
 *
 * $this->actingWithBrand(['name' => 'Acme', 'colors' => ['primary' => '#000']]);
 * ```
 *
 * No database, no configuration, and whichever driver the application uses:
 * the brand is registered at runtime and activated. Whatever you activate is
 * dropped after each test.
 */
trait InteractsWithBrands
{
    /**
     * Run the rest of the test with a brand active.
     *
     * Pass an identifier to activate a brand the driver already knows, or an
     * array to define one on the spot. A defined brand still falls back to the
     * default brand for anything it leaves out.
     *
     * @param  array<array-key, mixed>|string  $brand
     */
    protected function actingWithBrand(array|string $brand, string $id = 'testing'): Brand
    {
        $whitelabel = $this->whitelabel();

        if (is_array($brand)) {
            $whitelabel->define($id, $brand);

            $brand = $id;
        }

        return $whitelabel->activate($brand);
    }

    /**
     * Define a brand without activating it.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function defineBrand(string $id, array $definition = []): Brand
    {
        return $this->whitelabel()->define($id, $definition);
    }

    /**
     * Arrange for the active brand, and every brand defined during the test,
     * to be dropped when the test ends.
     *
     * Registered against the application's own teardown rather than run from
     * an `#[After]` hook: those run once the application has already been
     * destroyed, where there is no container left to flush anything on.
     */
    #[Before]
    protected function flushBrandsAfterTest(): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            $this->whitelabel()->flush();
        });
    }

    /**
     * Provided by Laravel's own test case, which this trait is used inside.
     *
     * @param  callable(): void  $callback
     */
    abstract protected function beforeApplicationDestroyed(callable $callback);

    private function whitelabel(): Whitelabel
    {
        return app(Whitelabel::class);
    }
}
