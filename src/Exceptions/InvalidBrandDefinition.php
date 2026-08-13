<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use InvalidArgumentException;

final class InvalidBrandDefinition extends InvalidArgumentException implements WhitelabelException
{
    public static function at(string $brandId, string $path, string $problem): self
    {
        return new self(sprintf(
            'Brand [%s] has an invalid definition: [%s] %s.',
            $brandId,
            $path,
            $problem,
        ));
    }

    public static function explicitNull(string $brandId, string $path): self
    {
        return new self(sprintf(
            'Brand [%s] sets [%s] to null. Remove the key to fall back to the default brand, '
            .'or use an empty value to clear it.',
            $brandId,
            $path,
        ));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknownKey(string $brandId, string $path, array $known): self
    {
        sort($known);

        return new self(sprintf(
            'Brand [%s] defines an unknown key [%s]. Expected one of: %s.',
            $brandId,
            $path,
            implode(', ', array_map(static fn (string $key): string => "[{$key}]", $known)),
        ));
    }
}
