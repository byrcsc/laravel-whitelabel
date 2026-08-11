<?php

declare(strict_types=1);

use Byrcsc\Whitelabel\Tests\Fixtures\BrandedMarkdownMail;
use Byrcsc\Whitelabel\Tests\Fixtures\BrandedNotification;
use Byrcsc\Whitelabel\Tests\Fixtures\PlainBrandMail;
use Byrcsc\Whitelabel\Tests\Fixtures\PlainBrandNotification;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Console\Command;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    Storage::fake('public');

    config()->set('mail.default', 'array');
    config()->set('mail.from', ['address' => 'app@example.test', 'name' => 'The Application']);
    config()->set('queue.default', 'database');
    config()->set('multitenancy.queues_are_tenant_aware_by_default', false);

    config()->set('whitelabel.default', 'default');
    config()->set('whitelabel.brands', [
        'default' => ['name' => 'Default'],
        'acme' => [
            'name' => 'Acme',
            'colors' => ['primary' => '#7c3aed'],
            'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
            'mail' => ['from_name' => 'Acme Support', 'from_address' => 'hello@acme.test'],
        ],
        'plain' => ['name' => 'Plain'],
    ]);
});

function mailWhitelabel(): Whitelabel
{
    return app(Whitelabel::class);
}

/**
 * The one message the array mailer has been handed.
 */
function lastEmail(): ?Email
{
    $transport = Mail::mailer()->getSymfonyTransport();

    if (! $transport instanceof ArrayTransport) {
        return null;
    }

    $last = $transport->messages()->last();

    if (! $last instanceof SentMessage) {
        return null;
    }

    $message = $last->getOriginalMessage();

    return $message instanceof Email ? $message : null;
}

function lastEmailBody(): string
{
    return (string) lastEmail()?->getHtmlBody();
}

describe('the brand in mail views', function (): void {
    it('is available in a mailable view', function (): void {
        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new PlainBrandMail);

        expect(lastEmailBody())->toContain('Brand: acme');
    });

    it('is available in a notification mail view', function (): void {
        mailWhitelabel()->activate('acme');

        Notification::route('mail', 'someone@example.test')->notify(new PlainBrandNotification);

        expect(lastEmailBody())->toContain('Brand: acme');
    });
});

describe('the branded markdown theme', function (): void {
    it('puts the brand logo in the header', function (): void {
        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        expect(lastEmailBody())
            ->toContain('brands/acme/logo.svg')
            ->toContain('alt="Acme"');
    });

    it('paints the primary button in the brand colour', function (): void {
        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        expect(lastEmailBody())->toContain('#7c3aed');
    });

    it('makes a root-relative logo path absolute, so a mail client can fetch it', function (): void {
        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        expect(lastEmailBody())->toContain(url('/').'/');
        expect(lastEmailBody())->not->toContain('src="/storage');
    });

    it('falls back to Laravel\'s own look when the brand has no logo or colours', function (): void {
        config()->set('app.name', 'Acme Software');

        mailWhitelabel()->activate('plain');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        $body = lastEmailBody();

        expect($body)->toContain('The brand is Plain.')
            ->toContain('Get started')
            ->toContain('Acme Software');
        expect($body)->not->toContain('<img');
    });

    it('keeps Laravel\'s own logo branch for an application named Laravel', function (): void {
        config()->set('app.name', 'Laravel');

        mailWhitelabel()->activate('plain');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        expect(lastEmailBody())->toContain('notification-logo');
    });

    it('leaves markdown mail alone when the branding is switched off', function (): void {
        config()->set('whitelabel.mail.markdown', false);

        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

        $body = lastEmailBody();

        expect($body)->toContain('The brand is Acme.');
        expect($body)->not->toContain('brands/acme/logo.svg');
        expect($body)->not->toContain('#7c3aed');
    });

    it('sits behind whatever the application published', function (): void {
        $published = resource_path('views/vendor/mail/html/header.blade.php');

        File::ensureDirectoryExists(dirname($published));
        File::put($published, "@props(['url'])\n<tr><td>the application own header</td></tr>");

        try {
            mailWhitelabel()->activate('acme');

            Mail::to('someone@example.test')->send(new BrandedMarkdownMail);

            $body = lastEmailBody();

            expect($body)->toContain('the application own header');
            expect($body)->not->toContain('brands/acme/logo.svg');
        } finally {
            File::deleteDirectory(resource_path('views/vendor/mail'));
        }
    });
});

describe('the sender override', function (): void {
    it('leaves the sender alone while the flag is off', function (): void {
        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new PlainBrandMail);

        expect(lastEmail()?->getFrom()[0]->getAddress())->toBe('app@example.test');
    });

    it('sends from the brand once the flag is on', function (): void {
        config()->set('whitelabel.mail.override_from', true);

        mailWhitelabel()->activate('acme');

        Mail::to('someone@example.test')->send(new PlainBrandMail);

        expect(lastEmail()?->getFrom()[0]->getAddress())->toBe('hello@acme.test')
            ->and(lastEmail()?->getFrom()[0]->getName())->toBe('Acme Support');
    });

    it('leaves a brand with no sender alone', function (): void {
        config()->set('whitelabel.mail.override_from', true);

        mailWhitelabel()->activate('plain');

        Mail::to('someone@example.test')->send(new PlainBrandMail);

        expect(lastEmail()?->getFrom()[0]->getAddress())->toBe('app@example.test');
    });

    it('leaves mail sent with no brand alone', function (): void {
        config()->set('whitelabel.mail.override_from', true);
        config()->set('whitelabel.resolvers', []);

        Mail::to('someone@example.test')->send(new PlainBrandMail);

        expect(lastEmail()?->getFrom()[0]->getAddress())->toBe('app@example.test');
    });
});

it('delivers a queued branded notification with the brand sender and branding', function (): void {
    config()->set('whitelabel.mail.override_from', true);

    mailWhitelabel()->activate('acme');

    Notification::route('mail', 'someone@example.test')->notify(new BrandedNotification);

    mailWhitelabel()->flush();

    expect(Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]))->toBe(Command::SUCCESS)
        ->and(lastEmail()?->getFrom()[0]->getAddress())->toBe('hello@acme.test')
        ->and(lastEmailBody())->toContain('Brand: acme')
        ->and(lastEmailBody())->toContain('brands/acme/logo.svg');
});
