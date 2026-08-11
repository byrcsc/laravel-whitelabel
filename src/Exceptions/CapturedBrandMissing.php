<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use RuntimeException;

/**
 * A queued job captured a brand that no longer exists.
 *
 * Thrown instead of falling back to the default brand, because a job that was
 * dispatched to be branded and runs unbranded sends the wrong-looking mail to
 * a real customer. Catch it in your queue failure handling, or let the job
 * fail and retry once the brand is back.
 */
final class CapturedBrandMissing extends RuntimeException implements WhitelabelException
{
    public static function named(string $id, string $job): self
    {
        return new self(sprintf(
            'The job [%s] was dispatched with the brand [%s] active, and that brand no longer exists. '
            .'Restore it, or dispatch the job again without it.',
            $job,
            $id,
        ));
    }
}
