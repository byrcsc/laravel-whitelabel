<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Queue\BrandAware;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A job that records which brand was active while it ran.
 */
class BrandedJob implements ShouldQueue
{
    use BrandAware;
    use Queueable;
    use SerializesModels;

    public static ?string $seen = null;

    public function handle(Whitelabel $whitelabel): void
    {
        self::$seen = $whitelabel->current()?->id();
    }
}
