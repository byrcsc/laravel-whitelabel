<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Facades\Whitelabel;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The same mailable as {@see BrandedMailable}, without the queue, for the
 * tests that send synchronously.
 */
class PlainBrandMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hello');
    }

    public function content(): Content
    {
        return new Content(htmlString: 'Brand: '.(Whitelabel::current()?->id() ?? 'none'));
    }
}
