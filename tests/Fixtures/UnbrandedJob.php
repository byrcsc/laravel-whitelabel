<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The same job without the trait, to prove opt-in is real.
 */
class UnbrandedJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public static ?string $seen = null;

    public function handle(Whitelabel $whitelabel): void
    {
        self::$seen = $whitelabel->current()?->id();
    }
}
