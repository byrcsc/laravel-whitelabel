<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Exceptions\CapturedBrandMissing;
use Byrcsc\Whitelabel\Queue\BrandContext;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandedJob;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandedMailable;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandedNotification;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandProbeEvent;
use Byrcsc\Whitelabel\Tests\Fixtures\RecordBrandOnEvent;
use Byrcsc\Whitelabel\Tests\Fixtures\RenamedBrandedJob;
use Byrcsc\Whitelabel\Tests\Fixtures\UnbrandedJob;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Console\Command;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\SentMessage;

beforeEach(function (): void {
    config()->set('queue.default', 'database');
    config()->set('mail.default', 'array');
    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => ['name' => 'Default'],
        'acme' => ['name' => 'Acme'],
    ]);

    // This file is about the path for applications that do not scope work to a
    // tenant. When Spatie is installed its default is to treat every job as
    // tenant-aware, which deletes any job dispatched without one.
    config()->set('multitenancy.queues_are_tenant_aware_by_default', false);

    Event::listen(BrandProbeEvent::class, RecordBrandOnEvent::class);

    BrandedJob::$seen = null;
    UnbrandedJob::$seen = null;
    RenamedBrandedJob::$seen = null;
    RecordBrandOnEvent::$seen = null;
});

/**
 * Run whatever is on the queue, in this process.
 */
function work(): int
{
    return Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
}

function queueWhitelabel(): Whitelabel
{
    return app(Whitelabel::class);
}

/**
 * Everything the array mailer has been handed, as one blob of text.
 */
function sentMail(): string
{
    $transport = Mail::mailer()->getSymfonyTransport();

    if (! $transport instanceof ArrayTransport) {
        return '';
    }

    $bodies = [];

    foreach ($transport->messages() as $message) {
        // Decoded, so an assertion cannot be defeated by a quoted-printable
        // soft break landing in the middle of the string being looked for.
        $bodies[] = $message instanceof SentMessage
            ? (string) quoted_printable_decode($message->toString())
            : '';
    }

    return implode("\n", $bodies);
}

/**
 * The decoded payload of the one job waiting on the queue.
 *
 * @return array<array-key, mixed>
 */
function queuedPayload(): array
{
    $row = DB::table('jobs')->first();
    $raw = is_object($row) && isset($row->payload) && is_string($row->payload) ? $row->payload : '{}';

    $payload = json_decode($raw, true);

    return is_array($payload) ? $payload : [];
}

/**
 * The reason a queued job ended up in `failed_jobs`, or an empty string.
 */
function lastFailure(): string
{
    $failure = DB::table('failed_jobs')->first();

    return is_object($failure) && property_exists($failure, 'exception') && is_string($failure->exception)
        ? $failure->exception
        : '';
}

it('runs a job with the brand that was active at dispatch', function (): void {
    queueWhitelabel()->activate('acme');

    dispatch(new BrandedJob);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(BrandedJob::$seen)->toBe('acme');
});

it('leaves a job without the trait to resolve for itself', function (): void {
    queueWhitelabel()->activate('acme');

    dispatch(new UnbrandedJob);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(UnbrandedJob::$seen)->toBe('default');
});

it('does nothing when no brand was active at dispatch', function (): void {
    config()->set('whitelabel.resolvers', []);

    dispatch(new BrandedJob);

    expect(work())->toBe(Command::SUCCESS)
        ->and(BrandedJob::$seen)->toBeNull();
});

it('stamps the outgoing payload with the active brand', function (): void {
    queueWhitelabel()->activate('acme');

    dispatch(new BrandedJob);

    expect(queuedPayload())->toHaveKey(BrandContext::PAYLOAD_KEY, 'acme');
});

it('stamps nothing when the chain resolves no brand', function (): void {
    config()->set('whitelabel.resolvers', []);

    dispatch(new BrandedJob);

    expect(queuedPayload())->not->toHaveKey(BrandContext::PAYLOAD_KEY);
});

it('restores the brand even when the job renames itself for the dashboard', function (): void {
    queueWhitelabel()->activate('acme');

    dispatch(new RenamedBrandedJob);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(RenamedBrandedJob::$seen)->toBe('acme');
});

it('runs a queued listener with the brand active when the event fired', function (): void {
    queueWhitelabel()->activate('acme');

    RecordBrandOnEvent::$seen = null;
    event(new BrandProbeEvent);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(RecordBrandOnEvent::$seen)->toBe('acme');
});

it('does not carry one job brand into the next', function (): void {
    queueWhitelabel()->activate('acme');
    dispatch(new BrandedJob);

    queueWhitelabel()->forget();
    dispatch(new UnbrandedJob);

    queueWhitelabel()->flush();

    expect(Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]))->toBe(Command::SUCCESS)
        ->and(BrandedJob::$seen)->toBe('acme')
        ->and(UnbrandedJob::$seen)->toBe('default');
});

it('fails with a dedicated exception when the captured brand has gone', function (): void {
    queueWhitelabel()->activate('acme');

    dispatch(new BrandedJob);

    // The brand is withdrawn between dispatch and the worker picking the job up.
    config()->set('whitelabel.brands', ['default' => ['name' => 'Default']]);
    queueWhitelabel()->flush();

    work();

    expect(lastFailure())->toContain(CapturedBrandMissing::class)
        ->toContain('the brand [acme] active, and that brand no longer exists')
        ->and(BrandedJob::$seen)->toBeNull();
});

it('renders a queued mailable with the brand active at dispatch', function (): void {
    queueWhitelabel()->activate('acme');

    Mail::to('someone@example.com')->queue(new BrandedMailable);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(sentMail())->toContain('Brand: acme');
});

it('renders a queued notification with the brand active at dispatch', function (): void {
    queueWhitelabel()->activate('acme');

    Notification::route('mail', 'someone@example.com')->notify(new BrandedNotification);

    queueWhitelabel()->flush();

    expect(work())->toBe(Command::SUCCESS)
        ->and(sentMail())->toContain('Brand: acme');
});

it('resolves the default brand in a console command with no override', function (): void {
    expect(queueWhitelabel()->current()?->id())->toBe('default');
});
