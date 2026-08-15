<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Feature;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandAsset;
use Byrcsc\Whitelabel\BrandDefinition;
use Byrcsc\Whitelabel\BrandRepositoryManager;
use Byrcsc\Whitelabel\Commands\ClearBrandCacheCommand;
use Byrcsc\Whitelabel\Commands\InstallCommand;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Contracts\ProvidesBrand;
use Byrcsc\Whitelabel\Database\Factories\BrandRecordFactory;
use Byrcsc\Whitelabel\Drivers\CachedBrandRepository;
use Byrcsc\Whitelabel\Drivers\ConfigBrandRepository;
use Byrcsc\Whitelabel\Drivers\DatabaseBrandRepository;
use Byrcsc\Whitelabel\Events\BrandActivated;
use Byrcsc\Whitelabel\Events\BrandCreated;
use Byrcsc\Whitelabel\Events\BrandDeactivated;
use Byrcsc\Whitelabel\Events\BrandDeleted;
use Byrcsc\Whitelabel\Events\BrandUpdated;
use Byrcsc\Whitelabel\Exceptions\BrandAlreadyExists;
use Byrcsc\Whitelabel\Exceptions\CapturedBrandMissing;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Exceptions\UnsupportedBrandOperation;
use Byrcsc\Whitelabel\Exceptions\WhitelabelException;
use Byrcsc\Whitelabel\Facades\Whitelabel as WhitelabelFacade;
use Byrcsc\Whitelabel\Http\Middleware\EagerResolveBrand;
use Byrcsc\Whitelabel\Mail\BrandedMarkdown;
use Byrcsc\Whitelabel\Models\BrandRecord;
use Byrcsc\Whitelabel\Queue\BrandAware;
use Byrcsc\Whitelabel\Resolvers\DefaultResolver;
use Byrcsc\Whitelabel\Resolvers\DomainResolver;
use Byrcsc\Whitelabel\Resolvers\OverrideResolver;
use Byrcsc\Whitelabel\Resolvers\TenantResolver;
use Byrcsc\Whitelabel\Testing\InteractsWithBrands;
use Byrcsc\Whitelabel\Tests\TestCase;
use Byrcsc\Whitelabel\View\Components\Favicon;
use Byrcsc\Whitelabel\View\Components\Logo;
use Byrcsc\Whitelabel\View\Components\Styles;
use Byrcsc\Whitelabel\Whitelabel;
use Byrcsc\Whitelabel\WhitelabelServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

/**
 * The public API, frozen.
 *
 * Every class and method here is documented in the versioned public API
 * reference and covered by the versioning promise in `CONTRIBUTING.md`: it
 * cannot be removed or renamed without a new major version. Removing one of
 * these breaks this test, which is the point.
 *
 * Adding to the package does not break this test. That is deliberate — the
 * test says what may not disappear, not what may not appear.
 */
class PublicApiTest extends TestCase
{
    /**
     * Every class an application may name, and the methods it may call.
     *
     * @return array<string, array{class-string, list<string>}>
     */
    public static function publicClasses(): array
    {
        return [
            // The brand itself.
            Brand::class => [Brand::class, [
                'get', 'has', 'id', 'name', 'domain', 'logo', 'favicon', 'asset',
                'colors', 'color', 'mailFromName', 'mailFromAddress', 'settings', 'setting',
                'logoUrl', 'faviconUrl', 'assetUrl',
                'definition', 'fallback', 'withFallback', 'toArray',
            ]],
            BrandAsset::class => [BrandAsset::class, ['fromDefinition', 'isAbsoluteUrl', 'url', 'toArray']],
            BrandDefinition::class => [BrandDefinition::class, ['validate', 'inherit']],

            // Resolution.
            Whitelabel::class => [Whitelabel::class, [
                'current', 'isResolved', 'activate', 'forget', 'flush',
                'define', 'find', 'findByDomain', 'overridden',
            ]],
            WhitelabelFacade::class => [WhitelabelFacade::class, []],
            BrandResolver::class => [BrandResolver::class, ['resolve']],
            OverrideResolver::class => [OverrideResolver::class, ['resolve']],
            TenantResolver::class => [TenantResolver::class, ['resolve']],
            DomainResolver::class => [DomainResolver::class, ['resolve']],
            DefaultResolver::class => [DefaultResolver::class, ['resolve']],
            EagerResolveBrand::class => [EagerResolveBrand::class, ['handle']],

            // Storage.
            BrandRepository::class => [BrandRepository::class, [
                'all', 'find', 'findByDomain', 'has', 'create', 'update', 'delete', 'flush',
            ]],
            BrandRepositoryManager::class => [BrandRepositoryManager::class, ['driver', 'extend', 'getDefaultDriver']],
            ConfigBrandRepository::class => [ConfigBrandRepository::class, []],
            DatabaseBrandRepository::class => [DatabaseBrandRepository::class, []],
            CachedBrandRepository::class => [CachedBrandRepository::class, ['inner']],

            // Events.
            BrandActivated::class => [BrandActivated::class, []],
            BrandDeactivated::class => [BrandDeactivated::class, []],
            BrandCreated::class => [BrandCreated::class, []],
            BrandUpdated::class => [BrandUpdated::class, []],
            BrandDeleted::class => [BrandDeleted::class, []],

            // Exceptions.
            WhitelabelException::class => [WhitelabelException::class, []],
            InvalidBrandDefinition::class => [InvalidBrandDefinition::class, []],
            UnknownBrand::class => [UnknownBrand::class, []],
            UnsupportedBrandOperation::class => [UnsupportedBrandOperation::class, []],
            BrandAlreadyExists::class => [BrandAlreadyExists::class, []],
            CapturedBrandMissing::class => [CapturedBrandMissing::class, []],

            // Queues, mail, and views.
            BrandAware::class => [BrandAware::class, []],
            BrandedMarkdown::class => [BrandedMarkdown::class, ['logoUrl', 'name', 'headingStyle', 'buttonStyle']],
            Styles::class => [Styles::class, ['render', 'shouldRender']],
            Logo::class => [Logo::class, ['render', 'shouldRender']],
            Favicon::class => [Favicon::class, ['render', 'shouldRender']],

            // Testing, and the one internal class it names.
            InteractsWithBrands::class => [InteractsWithBrands::class, ['actingWithBrand', 'defineBrand']],
            BrandRecord::class => [BrandRecord::class, ['factory']],
            BrandRecordFactory::class => [BrandRecordFactory::class, ['definition', 'identifiedBy', 'bare']],

            // Integration.
            ProvidesBrand::class => [ProvidesBrand::class, ['brand']],
            WhitelabelServiceProvider::class => [WhitelabelServiceProvider::class, []],
            InstallCommand::class => [InstallCommand::class, []],
            ClearBrandCacheCommand::class => [ClearBrandCacheCommand::class, []],
        ];
    }

    /**
     * @param  class-string  $class
     * @param  list<string>  $methods
     */
    #[Test]
    #[DataProvider('publicClasses')]
    public function it_is_public_api(string $class, array $methods): void
    {
        $this->assertTrue(
            class_exists($class) || interface_exists($class) || trait_exists($class),
            "[{$class}] is documented as public API and no longer exists.",
        );

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($class, $method),
                "[{$class}::{$method}()] is documented as public API and no longer exists.",
            );

            // A trait's helper is protected on purpose: a test case calls it
            // on itself, and making it public would put it on that test case's
            // own surface. Private, though, would put it out of reach.
            $reflected = new ReflectionMethod($class, $method);

            trait_exists($class)
                ? $this->assertFalse(
                    $reflected->isPrivate(),
                    "[{$class}::{$method}()] is documented as public API but is now private.",
                )
                : $this->assertTrue(
                    $reflected->isPublic(),
                    "[{$class}::{$method}()] is documented as public API but is no longer public.",
                );
        }
    }

    #[Test]
    public function the_spatie_switch_task_is_public_api_when_spatie_is_installed(): void
    {
        // Guarded rather than listed above: the class names Spatie types, so
        // naming it unconditionally would break the no-Spatie CI job.
        if (! interface_exists('Spatie\Multitenancy\Tasks\SwitchTenantTask')) {
            $this->markTestSkipped('spatie/laravel-multitenancy is not installed.');
        }

        /** @var class-string $task */
        $task = implode('\\', ['Byrcsc', 'Whitelabel', 'Spatie', 'SwitchTenantBrandTask']);

        $this->assertTrue(class_exists($task), "[{$task}] is documented as public API and no longer exists.");

        foreach (['makeCurrent', 'forgetCurrent'] as $method) {
            $this->assertTrue(
                method_exists($task, $method),
                "[{$task}::{$method}()] is documented as public API and no longer exists.",
            );
        }
    }

    #[Test]
    public function the_documented_publish_tags_exist(): void
    {
        foreach (['whitelabel-config', 'whitelabel-views', 'whitelabel-migrations'] as $tag) {
            $this->assertNotEmpty(
                ServiceProvider::pathsToPublish(WhitelabelServiceProvider::class, $tag),
                "[{$tag}] is documented as a publish tag and publishes nothing.",
            );
        }
    }

    #[Test]
    public function the_documented_commands_take_the_documented_options(): void
    {
        $install = (new InstallCommand)->getDefinition();

        $this->assertTrue($install->hasOption('database'));
        $this->assertTrue($install->hasOption('force'));

        $ours = [];

        foreach (Artisan::all() as $name => $command) {
            if (is_object($command) && str_starts_with($command::class, 'Byrcsc\\Whitelabel\\')) {
                $ours[] = $name;
            }
        }

        sort($ours);

        $this->assertSame(['whitelabel:clear', 'whitelabel:install'], $ours);
    }

    #[Test]
    public function every_event_carries_the_brand_it_is_about(): void
    {
        $brand = new Brand('acme', ['name' => 'Acme']);

        foreach ([
            BrandActivated::class, BrandDeactivated::class,
            BrandCreated::class, BrandUpdated::class, BrandDeleted::class,
        ] as $event) {
            $this->assertSame($brand, (new $event($brand))->brand);
        }
    }

    #[Test]
    public function the_styles_component_takes_a_prefix(): void
    {
        config()->set('whitelabel.brands', ['default' => ['colors' => ['primary' => '#000000']]]);

        $this->assertStringContainsString(
            '--theme-primary',
            Blade::render('<x-whitelabel::styles prefix="theme" />'),
        );
    }

    #[Test]
    public function the_brand_helper_is_public_api(): void
    {
        $this->assertTrue(function_exists('brand'));
    }

    #[Test]
    public function the_documented_components_render(): void
    {
        config()->set('whitelabel.brands', ['default' => [
            'name' => 'Surface',
            'colors' => ['primary' => '#000000'],
            'logo' => 'https://example.test/logo.svg',
            'favicon' => 'https://example.test/icon.svg',
        ]]);

        $rendered = Blade::render(
            '<x-whitelabel::styles /><x-whitelabel::logo /><x-whitelabel::favicon />'
        );

        $this->assertStringContainsString('--brand-primary', $rendered);
        $this->assertStringContainsString('<img', $rendered);
        $this->assertStringContainsString('rel="icon"', $rendered);
    }

    #[Test]
    public function the_documented_config_keys_exist(): void
    {
        foreach ([
            'whitelabel.driver',
            'whitelabel.default',
            'whitelabel.brands',
            'whitelabel.resolvers',
            'whitelabel.database.connection',
            'whitelabel.database.table',
            'whitelabel.cache.enabled',
            'whitelabel.cache.store',
            'whitelabel.cache.prefix',
            'whitelabel.assets.disk',
            'whitelabel.css.prefix',
            'whitelabel.mail.markdown',
            'whitelabel.mail.override_from',
        ] as $key) {
            $this->assertTrue(
                config()->has($key),
                "[{$key}] is documented in config/whitelabel.php and no longer exists.",
            );
        }
    }

    #[Test]
    public function the_eloquent_model_is_not_part_of_the_public_api(): void
    {
        // The factory is reachable, and that is the whole of it: nothing the
        // package hands back is ever a BrandRecord.
        $returns = [];

        foreach ((new ReflectionClass(BrandRepository::class))->getMethods() as $method) {
            $returns[] = (string) $method->getReturnType();
        }

        foreach ((new ReflectionClass(Whitelabel::class))->getMethods() as $method) {
            if ($method->isPublic()) {
                $returns[] = (string) $method->getReturnType();
            }
        }

        foreach ($returns as $return) {
            $this->assertStringNotContainsString(
                'BrandRecord',
                $return,
                'The internal Eloquent model leaked into the public API.',
            );
        }
    }

    /**
     * Not a guard against internals leaking — the list below is the same one
     * the test declares. It is a guard against *forgetting*: adding a public
     * method to the manager fails here until it is listed above, which means
     * documenting it in the public API reference.
     */
    #[Test]
    public function every_public_manager_method_is_declared_public_api(): void
    {
        /** @var list<string> $documented */
        $documented = self::publicClasses()[Whitelabel::class][1];

        $undocumented = [];

        foreach ((new ReflectionClass(Whitelabel::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isConstructor() && ! in_array($method->getName(), $documented, true)) {
                $undocumented[] = $method->getName();
            }
        }

        $this->assertSame(
            [],
            $undocumented,
            'Whitelabel has public methods the README does not document: '.implode(', ', $undocumented),
        );
    }
}
