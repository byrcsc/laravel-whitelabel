<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use RuntimeException;

/**
 * A brand was named but no driver could produce it.
 */
final class UnknownBrand extends RuntimeException implements WhitelabelException
{
    /**
     * @param  list<string>  $known
     */
    public static function defaultBrand(string $id, array $known): self
    {
        sort($known);

        return new self(sprintf(
            'whitelabel.default names the brand [%s], which is not defined. Define it, '
            .'or point whitelabel.default at one of: %s.',
            $id,
            implode(', ', array_map(static fn (string $brand): string => "[{$brand}]", $known)),
        ));
    }

    public static function named(string $id): self
    {
        return new self(sprintf('There is no brand with the identifier [%s].', $id));
    }
}
