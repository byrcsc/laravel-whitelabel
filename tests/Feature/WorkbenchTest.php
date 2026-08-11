<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Feature;

use Byrcsc\Whitelabel\BrandRepositoryManager;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Testing\InteractsWithBrands;
use Byrcsc\Whitelabel\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Workbench\App\Models\Tenant;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Drives the workbench's own routes, so its README describes something that
 * works rather than something that used to.
 *
 * Grouped with the multitenancy tests: the tenant route needs Spatie.
 */
#[Group('multitenancy')]
class WorkbenchTest extends TestCase
{
    use InteractsWithBrands;

    /**
     * The demo's own provider, on top of the package's.
     *
     * Added here rather than in the shared test case: it rewrites
     * `whitelabel.brands` for the whole application, which is what the demo
     * wants and what every other test would have to undo.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), WorkbenchServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * One request per test, the way a browser does it: the active brand lives
     * for one request, and three `get()` calls in one test share a container
     * that a real request boundary would have thrown away.
     */
    #[Test]
    public function the_acme_host_gets_the_acme_brand(): void
    {
        $this->get('http://acme.localhost/')->assertOk()->assertSee('Acme');
    }

    #[Test]
    public function the_globex_host_gets_the_globex_brand(): void
    {
        $this->get('http://globex.localhost/')->assertOk()->assertSee('Globex');
    }

    #[Test]
    public function an_unclaimed_host_gets_the_default_brand(): void
    {
        $this->get('http://localhost/')->assertOk()->assertSee('Whitelabel Demo');
    }

    #[Test]
    public function a_partial_brand_shows_what_it_inherited(): void
    {
        $this->get('http://globex.localhost/')
            ->assertOk()
            ->assertSee('#0ea5e9')                          // its own primary
            ->assertSee('#6b7280')                          // the default's secondary
            ->assertSee('inherited from the default brand')
            ->assertSee('brands/default/logo.svg');         // the default's logo
    }

    #[Test]
    public function the_three_components_render_in_the_layout(): void
    {
        $page = $this->get('http://acme.localhost/')->assertOk()->getContent();

        $this->assertIsString($page);
        $this->assertStringContainsString('--brand-primary:#7c3aed;', $page);
        $this->assertStringContainsString('brands/acme/logo.svg', $page);
        $this->assertStringContainsString('rel="icon"', $page);
    }

    #[Test]
    public function an_explicit_activation_beats_the_host(): void
    {
        $this->get('http://acme.localhost/as/globex')->assertOk()->assertSee('Globex');
    }

    #[Test]
    public function a_tenant_becoming_current_activates_its_brand(): void
    {
        Tenant::query()->create([
            'name' => 'Initech',
            'slug' => 'initech',
            'brand' => ['name' => 'Initech', 'colors' => ['primary' => '#dc2626']],
        ]);

        $this->get('http://localhost/tenant/initech')
            ->assertOk()
            ->assertSee('Initech')
            ->assertSee('#dc2626');
    }

    #[Test]
    public function the_middleware_shares_the_brand_with_the_view(): void
    {
        $this->get('http://acme.localhost/eager')
            ->assertOk()
            ->assertSee('shared this brand with the view');
    }

    #[Test]
    public function brands_can_be_managed_through_the_database_driver(): void
    {
        /** @var BrandRepository $stored */
        $stored = app(BrandRepositoryManager::class)->driver('database');

        $stored->create('umbrella', [
            'name' => 'Umbrella',
            'domain' => 'umbrella.localhost',
            'colors' => ['primary' => '#16a34a'],
        ]);

        $this->assertSame('Umbrella', $stored->find('umbrella')?->name());
        $this->assertSame('umbrella', $stored->findByDomain('umbrella.localhost')?->id());

        $stored->update('umbrella', ['name' => 'Umbrella Corp']);
        $this->assertSame('Umbrella Corp', $stored->find('umbrella')->name());

        $this->assertTrue($stored->delete('umbrella'));
        $this->assertNull($stored->find('umbrella'));
    }

    #[Test]
    public function the_notification_route_queues_branded_mail(): void
    {
        config()->set('mail.default', 'array');

        $this->get('http://acme.localhost/notify')->assertOk()->assertSee('Queued');

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]),
        );

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        $last = $transport->messages()->last();
        $this->assertInstanceOf(SentMessage::class, $last);

        // Decoded: the raw MIME is quoted-printable, which will happily put a
        // soft line break in the middle of a URL.
        $sent = quoted_printable_decode($last->toString());

        $this->assertStringContainsString('Welcome to Acme', $sent);
        $this->assertStringContainsString('brands/acme/logo.svg', $sent);
        $this->assertStringContainsString('#7c3aed', $sent);
    }

    #[Test]
    public function the_testing_helpers_work_against_the_workbench(): void
    {
        $this->actingWithBrand(['name' => 'Ad hoc', 'colors' => ['primary' => '#111111']]);

        $this->get('http://acme.localhost/')
            ->assertOk()
            ->assertSee('Ad hoc')
            ->assertSee('#111111');
    }
}
