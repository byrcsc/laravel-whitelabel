<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Feature;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Events\BrandCreated;
use Byrcsc\Whitelabel\Facades\Whitelabel;
use Byrcsc\Whitelabel\Models\BrandRecord;
use Byrcsc\Whitelabel\Testing\InteractsWithBrands;
use Byrcsc\Whitelabel\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

/**
 * Written as a class rather than in Pest's style on purpose: this is the
 * package's own imitation of a user's test suite, and the trait is used the
 * way a user would use it.
 */
class InteractsWithBrandsTest extends TestCase
{
    use InteractsWithBrands;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whitelabel.default', 'default');
        config()->set('whitelabel.brands', [
            'default' => ['name' => 'Default', 'colors' => ['primary' => '#000000']],
            'acme' => ['name' => 'Acme'],
        ]);
    }

    #[Test]
    public function it_defines_and_activates_a_brand_with_no_database_in_sight(): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $brand = $this->actingWithBrand(['name' => 'Acme', 'colors' => ['primary' => '#7c3aed']]);

        $this->assertSame('testing', $brand->id());
        $this->assertSame('Acme', brand('name'));
        $this->assertSame('#7c3aed', brand('colors.primary'));

        $queries = DB::connection()->getQueryLog();

        DB::connection()->disableQueryLog();

        // Not "no rows": no database at all.
        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_names_the_defined_brand_when_asked(): void
    {
        $this->assertSame('acme-test', $this->actingWithBrand(['name' => 'Acme'], 'acme-test')->id());
    }

    #[Test]
    public function it_activates_a_brand_the_driver_already_knows(): void
    {
        $this->assertSame('Acme', $this->actingWithBrand('acme')->name());
    }

    #[Test]
    public function it_still_falls_back_to_the_default_brand(): void
    {
        $this->actingWithBrand(['name' => 'Acme']);

        $this->assertSame('#000000', brand('colors.primary'));
    }

    #[Test]
    public function it_defines_a_brand_without_activating_it(): void
    {
        $this->defineBrand('spare', ['name' => 'Spare']);

        $this->assertSame('Spare', Whitelabel::find('spare')?->name());
        $this->assertSame('default', Whitelabel::current()?->id());
    }

    #[Test]
    public function it_leaves_nothing_behind_part_one(): void
    {
        $this->actingWithBrand(['name' => 'Leaky'], 'leaky');

        $this->assertSame('Leaky', brand('name'));
    }

    #[Test]
    public function it_leaves_nothing_behind_part_two(): void
    {
        $this->assertNull(Whitelabel::find('leaky'));
        $this->assertSame('Default', brand('name'));
    }

    #[Test]
    public function it_flushes_while_the_application_is_still_alive(): void
    {
        $this->actingWithBrand(['name' => 'Acme'], 'acme-test');

        $this->assertSame('Acme', brand('name'));

        // Exactly what Laravel runs at the end of a test, before the
        // application goes away. Parts one and two above would also pass if
        // the trait did nothing and the application were simply rebuilt; this
        // is what proves the trait itself cleans up.
        $this->callBeforeApplicationDestroyedCallbacks();

        $this->assertNull(Whitelabel::find('acme-test'));
        $this->assertSame('Default', brand('name'));
    }

    #[Test]
    public function it_keeps_package_events_assertable_with_a_plain_event_fake(): void
    {
        config()->set('whitelabel.driver', 'database');

        Event::fake([BrandCreated::class]);

        app(BrandRepository::class)->create('acme', ['name' => 'Acme']);

        Event::assertDispatched(
            BrandCreated::class,
            fn (BrandCreated $event): bool => $event->brand->id() === 'acme',
        );
    }

    #[Test]
    public function it_exposes_the_model_factory_to_a_user_test_suite(): void
    {
        config()->set('whitelabel.driver', 'database');

        BrandRecord::factory()->identifiedBy('globex')->create(['name' => 'Globex']);

        $this->assertSame('Globex', Whitelabel::find('globex')?->name());
    }
}
