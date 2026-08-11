<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A job that writes down which brand was active while it ran.
 *
 * A static property rather than a return value: the assertion happens in the
 * test process after the worker has finished, and the worker runs in-process
 * under `queue:work --once`.
 */
class RecordActiveBrand implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public static ?string $seen = null;

    public function handle(Whitelabel $whitelabel): void
    {
        self::$seen = $whitelabel->current()?->name();
    }
}
