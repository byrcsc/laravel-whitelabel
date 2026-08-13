<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Facades\Whitelabel;
use Byrcsc\Whitelabel\Queue\BrandAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A queued notification that renders the brand active while it was sent.
 */
class BrandedNotification extends Notification implements ShouldQueue
{
    use BrandAware;
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hello')
            ->line('Brand: '.(Whitelabel::current()?->id() ?? 'none'));
    }
}
