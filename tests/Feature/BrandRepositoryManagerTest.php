<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandRepositoryManager;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\ConfigBrandRepository;
use Byrcsc\Whitelabel\Tests\Fixtures\ArrayBrandRepository;
use InvalidArgumentException;

it('resolves the config driver by default', function (): void {
    expect(app(BrandRepository::class))->toBeInstanceOf(ConfigBrandRepository::class);
});

it('resolves the contract to the driver named in config', function (): void {
    config()->set('whitelabel.driver', 'array');

    app(BrandRepositoryManager::class)->extend(
        'array',
        fn (): BrandRepository => new ArrayBrandRepository(['acme' => new Brand('acme', ['name' => 'Acme'])]),
    );

    $repository = app(BrandRepositoryManager::class)->driver();

    expect($repository)->toBeInstanceOf(ArrayBrandRepository::class)
        ->and($repository->find('acme')?->name())->toBe('Acme');
});

it('uses a custom driver fully through the contract', function (): void {
    config()->set('whitelabel.driver', 'array');

    app(BrandRepositoryManager::class)->extend(
        'array',
        fn (): BrandRepository => new ArrayBrandRepository,
    );

    $repository = app(BrandRepositoryManager::class)->driver();

    $created = $repository->create('acme', ['name' => 'Acme', 'domain' => 'app.acme.com']);

    expect($created->name())->toBe('Acme')
        ->and($repository->has('acme'))->toBeTrue()
        ->and($repository->findByDomain('app.acme.com')?->id())->toBe('acme')
        ->and($repository->update('acme', ['name' => 'Acme Inc'])->name())->toBe('Acme Inc')
        ->and(array_keys($repository->all()))->toBe(['acme'])
        ->and($repository->delete('acme'))->toBeTrue()
        ->and($repository->find('acme'))->toBeNull();
});

it('fails loudly when the configured driver is not registered', function (): void {
    config()->set('whitelabel.driver', 'redis');

    expect(fn () => app(BrandRepositoryManager::class)->driver())
        ->toThrow(InvalidArgumentException::class, 'Driver [redis] not supported.');
});
