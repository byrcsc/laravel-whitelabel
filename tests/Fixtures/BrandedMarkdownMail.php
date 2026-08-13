<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A markdown mailable with a header and a primary button, so the branded
 * markdown components have something to brand.
 */
class BrandedMarkdownMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return new Content(markdown: 'whitelabel-tests::welcome');
    }
}
