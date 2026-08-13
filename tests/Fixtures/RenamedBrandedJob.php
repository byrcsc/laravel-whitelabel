<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Queue\BrandAware;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A branded job that gives itself a human label for the queue dashboard, which
 * is what a job's displayName() is normally for.
 */
class RenamedBrandedJob implements ShouldQueue
{
    use BrandAware;
    use Queueable;
    use SerializesModels;

    public static ?string $seen = null;

    public function displayName(): string
    {
        return 'Send the welcome email';
    }

    public function handle(Whitelabel $whitelabel): void
    {
        self::$seen = $whitelabel->current()?->id();
    }
}
