<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Facades\Whitelabel;

if (! function_exists('brand')) {
    /**
     * The active brand, or one of its values by dot-notation key.
     *
     * `brand()` returns the Brand itself, or null when nothing resolved.
     * `brand('name')` and `brand('settings.support_url')` read through it.
     *
     * This goes through the facade, so the helper and `Whitelabel::current()`
     * are the same call and a swapped facade root reaches both.
     */
    function brand(?string $key = null, mixed $default = null): mixed
    {
        $current = Whitelabel::current();

        if ($key === null) {
            return $current;
        }

        return $current instanceof Brand ? $current->get($key, $default) : $default;
    }
}
