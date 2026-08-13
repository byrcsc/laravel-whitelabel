<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Queue\BrandAware;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A queued listener that records the brand active while it ran.
 */
class RecordBrandOnEvent implements ShouldQueue
{
    use BrandAware;
    use InteractsWithQueue;

    public static ?string $seen = null;

    public function handle(BrandProbeEvent $event): void
    {
        self::$seen = app(Whitelabel::class)->current()?->id();
    }
}
