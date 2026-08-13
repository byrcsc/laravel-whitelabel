<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Facades;

use Byrcsc\Whitelabel\Brand;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Brand|null current()
 * @method static bool isResolved()
 * @method static Brand activate(Brand|string $brand)
 * @method static void forget()
 * @method static void flush()
 * @method static Brand define(string $id, array<array-key, mixed> $definition = [])
 * @method static Brand|null find(string $id)
 * @method static Brand|null findByDomain(string $domain)
 * @method static Brand|null overridden()
 *
 * @see \Byrcsc\Whitelabel\Whitelabel
 */
class Whitelabel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Byrcsc\Whitelabel\Whitelabel::class;
    }
}
