<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Facades\Whitelabel;
use Byrcsc\Whitelabel\Queue\BrandAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A queued mailable that renders the brand active while it was sent.
 */
class BrandedMailable extends Mailable implements ShouldQueue
{
    use BrandAware;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hello');
    }

    public function content(): Content
    {
        return new Content(htmlString: 'Brand: '.(Whitelabel::current()?->id() ?? 'none'));
    }
}
