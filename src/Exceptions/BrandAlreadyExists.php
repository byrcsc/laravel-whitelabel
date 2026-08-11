<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A write would have collided with a brand that is already stored.
 */
final class BrandAlreadyExists extends RuntimeException implements WhitelabelException
{
    public static function withIdentifier(string $id, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'A brand with the identifier [%s] already exists. Update it instead of creating it.',
            $id,
        ), previous: $previous);
    }

    public static function onDomain(string $domain, string $id, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'The domain [%s] already belongs to another brand, so it cannot be given to [%s]. '
            .'A domain identifies exactly one brand.',
            $domain,
            $id,
        ), previous: $previous);
    }
}
