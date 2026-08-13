<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Exceptions;

use Throwable;

/**
 * Implemented by every exception the package throws, so an application can
 * catch all of them with one type.
 */
interface WhitelabelException extends Throwable {}
