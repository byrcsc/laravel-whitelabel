<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use BadMethodCallException;

final class UnsupportedBrandOperation extends BadMethodCallException implements WhitelabelException
{
    public static function write(string $driver, string $operation): self
    {
        return new self(sprintf(
            'The [%s] brand driver is read-only and cannot [%s] brands. '
            .'Switch whitelabel.driver to a writable driver, such as [database].',
            $driver,
            $operation,
        ));
    }
}
