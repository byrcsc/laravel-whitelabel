<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Events\BrandActivated;
use Byrcsc\Whitelabel\Events\BrandDeactivated;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Facades\Whitelabel as WhitelabelFacade;
use Byrcsc\Whitelabel\Resolvers\DefaultResolver;
use Byrcsc\Whitelabel\Resolvers\DomainResolver;
use Byrcsc\Whitelabel\Resolvers\OverrideResolver;
use Byrcsc\Whitelabel\Resolvers\TenantResolver;
use Byrcsc\Whitelabel\Tests\Fixtures\FixedBrandResolver;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => ['name' => 'Default', 'colors' => ['primary' => '#000000']],
        'acme' => ['name' => 'Acme', 'domain' => 'app.acme.com'],
        'globex' => ['name' => 'Globex', 'domain' => 'app.globex.com'],
    ]);
});

function whitelabel(): Whitelabel
{
    return app(Whitelabel::class);
}

describe('the chain', function (): void {
    it('lands on the default brand when nothing else answers', function (): void {
        expect(whitelabel()->current()?->id())->toBe('default');
    });

    it('does not resolve until the brand is read', function (): void {
        expect(whitelabel()->isResolved())->toBeFalse();

        whitelabel()->current();

        expect(whitelabel()->isResolved())->toBeTrue();
    });

    it('runs the chain once and keeps the answer', function (): void {
        config()->set('whitelabel.resolvers', [FixedBrandResolver::class]);

        FixedBrandResolver::$calls = 0;
        FixedBrandResolver::$brand = new Brand('counted', ['name' => 'Counted']);

        whitelabel()->current();
        whitelabel()->current();

        expect(FixedBrandResolver::$calls)->toBe(1);
    });

    it('stops at the first resolver that answers', function (): void {
        config()->set('whitelabel.resolvers', [FixedBrandResolver::class, DefaultResolver::class]);

        FixedBrandResolver::$calls = 0;
        FixedBrandResolver::$brand = new Brand('first', ['name' => 'First']);

        expect(whitelabel()->current()?->id())->toBe('first');
    });

    it('walks past a resolver that cannot answer', function (): void {
        config()->set('whitelabel.resolvers', [FixedBrandResolver::class, DefaultResolver::class]);

        FixedBrandResolver::$calls = 0;
        FixedBrandResolver::$brand = null;

        expect(whitelabel()->current()?->id())->toBe('default');
    });

    it('resolves nothing when the chain is empty', function (): void {
        config()->set('whitelabel.resolvers', []);

        expect(whitelabel()->current())->toBeNull();
    });

    it('ignores an entry that is not a resolver', function (): void {
        config()->set('whitelabel.resolvers', [stdClass::class, DefaultResolver::class]);

        expect(whitelabel()->current()?->id())->toBe('default');
    });

    it('lets the configured order decide, not the kind of resolver', function (): void {
        config()->set('whitelabel.resolvers', [
            DomainResolver::class,
            OverrideResolver::class,
            DefaultResolver::class,
        ]);

        Route::get('/whose-brand', function (): string {
            whitelabel()->activate('acme');

            $name = brand('name');

            return is_string($name) ? $name : 'none';
        });

        // The override would win under the shipped order; here the domain
        // resolver is ahead of it, so the host decides.
        get('http://app.globex.com/whose-brand')->assertSee('Globex');
    });

    it('builds a resolver only when its turn comes', function (): void {
        config()->set('whitelabel.resolvers', [OverrideResolver::class, FixedBrandResolver::class]);

        FixedBrandResolver::$calls = 0;
        FixedBrandResolver::$brand = null;

        whitelabel()->activate('acme');

        expect(FixedBrandResolver::$calls)->toBe(0);
    });

    it('ships the documented default order', function (): void {
        expect(config('whitelabel.resolvers'))->toBe([
            OverrideResolver::class,
            TenantResolver::class,
            DomainResolver::class,
            DefaultResolver::class,
        ]);
    });
});

describe('the shipped resolvers', function (): void {
    it('answers with nothing from the override resolver until a brand is activated', function (): void {
        expect(app(OverrideResolver::class)->resolve())->toBeNull();

        whitelabel()->activate('acme');

        expect(app(OverrideResolver::class)->resolve()?->id())->toBe('acme');
    });

    it('answers with nothing from the tenant resolver without Spatie installed', function (): void {
        expect(app(TenantResolver::class)->resolve())->toBeNull();
    });

    it('answers with nothing from the domain resolver outside a request', function (): void {
        expect(app(DomainResolver::class)->resolve())->toBeNull();
    });

    it('matches the request host in the domain resolver', function (): void {
        Route::get('/whose-brand', function (): string {
            $name = brand('name');

            return is_string($name) ? $name : 'none';
        });

        get('http://app.globex.com/whose-brand')->assertSee('Globex');
    });

    it('answers with nothing from the domain resolver for an unclaimed host', function (): void {
        Route::get('/whose-brand', function (): string {
            $name = brand('name');

            return is_string($name) ? $name : 'none';
        });

        get('http://unclaimed.test/whose-brand')->assertSee('Default');
    });

    it('answers with nothing from the default resolver when none is configured', function (): void {
        config()->set('whitelabel.default', '');

        expect(app(DefaultResolver::class)->resolve())->toBeNull();
    });
});

describe('override and forget', function (): void {
    it('activates a brand by identifier, beating every other resolver', function (): void {
        expect(whitelabel()->activate('acme')->id())->toBe('acme')
            ->and(whitelabel()->current()?->id())->toBe('acme');
    });

    it('activates a brand instance directly', function (): void {
        $brand = new Brand('adhoc', ['name' => 'Ad hoc']);

        expect(whitelabel()->activate($brand)->id())->toBe('adhoc')
            ->and(whitelabel()->current())->toBe($brand);
    });

    it('refuses to activate an identifier that names no brand', function (): void {
        expect(fn () => whitelabel()->activate('nope'))
            ->toThrow(UnknownBrand::class, 'There is no brand with the identifier [nope].');
    });

    it('re-resolves after forgetting', function (): void {
        whitelabel()->activate('acme');
        whitelabel()->forget();

        expect(whitelabel()->isResolved())->toBeFalse()
            ->and(whitelabel()->current()?->id())->toBe('default');
    });
});

describe('lifecycle events', function (): void {
    it('fires activation once when the chain resolves', function (): void {
        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        whitelabel()->current();
        whitelabel()->current();

        Event::assertDispatchedTimes(BrandActivated::class, 1);
        Event::assertNotDispatched(BrandDeactivated::class);
    });

    it('fires deactivation then activation when a brand replaces another', function (): void {
        whitelabel()->current();

        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        whitelabel()->activate('acme');

        Event::assertDispatched(
            BrandDeactivated::class,
            fn (BrandDeactivated $event): bool => $event->brand->id() === 'default',
        );
        Event::assertDispatched(
            BrandActivated::class,
            fn (BrandActivated $event): bool => $event->brand->id() === 'acme',
        );
    });

    it('fires nothing when the same brand is activated again', function (): void {
        whitelabel()->activate('acme');

        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        whitelabel()->activate('acme');

        Event::assertNothingDispatched();
    });

    it('fires deactivation on forget', function (): void {
        whitelabel()->activate('acme');

        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        whitelabel()->forget();

        Event::assertDispatchedTimes(BrandDeactivated::class, 1);
        Event::assertNotDispatched(BrandActivated::class);
    });

    it('fires nothing when forgetting with no brand active', function (): void {
        Event::fake([BrandActivated::class, BrandDeactivated::class]);

        whitelabel()->forget();

        Event::assertNothingDispatched();
    });
});

describe('the facade and the helper', function (): void {
    it('exposes the same surface through the facade', function (): void {
        expect(WhitelabelFacade::current()?->id())->toBe('default')
            ->and(WhitelabelFacade::activate('acme')->id())->toBe('acme')
            ->and(WhitelabelFacade::isResolved())->toBeTrue();
    });

    it('reads the brand and its values through the helper', function (): void {
        whitelabel()->activate('acme');

        expect(brand())->toBeInstanceOf(Brand::class)
            ->and(brand('name'))->toBe('Acme')
            ->and(brand('colors.primary'))->toBe('#000000')
            ->and(brand('colors.tertiary', '#fallback'))->toBe('#fallback');
    });

    it('returns the given default from the helper when no brand resolves', function (): void {
        config()->set('whitelabel.resolvers', []);

        expect(brand())->toBeNull()
            ->and(brand('name', 'Nothing'))->toBe('Nothing');
    });
});

describe('runtime definitions', function (): void {
    it('defines a brand with no driver involvement', function (): void {
        $brand = whitelabel()->define('adhoc', ['name' => 'Ad hoc']);

        expect($brand->name())->toBe('Ad hoc')
            ->and($brand->color('primary'))->toBe('#000000')
            ->and(whitelabel()->find('adhoc')?->toArray())->toBe($brand->toArray())
            ->and(whitelabel()->activate('adhoc')->id())->toBe('adhoc');
    });

    it('prefers a defined brand over the driver', function (): void {
        whitelabel()->define('acme', ['name' => 'Redefined']);

        expect(whitelabel()->find('acme')?->name())->toBe('Redefined');
    });

    it('finds a defined brand by its domain', function (): void {
        whitelabel()->define('adhoc', ['name' => 'Ad hoc', 'domain' => 'app.adhoc.test']);

        expect(whitelabel()->findByDomain('APP.ADHOC.TEST')?->id())->toBe('adhoc')
            ->and(whitelabel()->findByDomain('app.acme.com')?->id())->toBe('acme');
    });

    it('never freezes the default brand a defined brand was born with', function (): void {
        whitelabel()->define('adhoc', ['name' => 'Ad hoc']);
        whitelabel()->define('default', ['name' => 'Default', 'colors' => ['primary' => '#ffffff']]);

        expect(whitelabel()->find('adhoc')?->color('primary'))->toBe('#ffffff');
    });

    it('drops defined brands and the active one on flush', function (): void {
        whitelabel()->define('adhoc', ['name' => 'Ad hoc']);
        whitelabel()->activate('adhoc');

        whitelabel()->flush();

        expect(whitelabel()->find('adhoc'))->toBeNull()
            ->and(whitelabel()->current()?->id())->toBe('default');
    });

    it('forgets the brand between queued jobs', function (): void {
        whitelabel()->activate('acme');

        event(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

        expect(whitelabel()->isResolved())->toBeFalse()
            ->and(whitelabel()->current()?->id())->toBe('default');
    });
});

it('resolves a custom resolver registered through config', function (): void {
    config()->set('whitelabel.resolvers', [FixedBrandResolver::class]);

    FixedBrandResolver::$calls = 0;
    FixedBrandResolver::$brand = new Brand('custom', ['name' => 'Custom']);

    expect(whitelabel()->current()?->name())->toBe('Custom')
        ->and(app(FixedBrandResolver::class))->toBeInstanceOf(BrandResolver::class);
});
