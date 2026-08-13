<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\CachedBrandRepository;
use Byrcsc\Whitelabel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The driver behind the caching decorator, or the driver itself when caching
 * is off. Lets a test assert which driver is in play without caring whether it
 * happens to be wrapped.
 */
function innerRepository(BrandRepository $repository): BrandRepository
{
    return $repository instanceof CachedBrandRepository ? $repository->inner() : $repository;
}
