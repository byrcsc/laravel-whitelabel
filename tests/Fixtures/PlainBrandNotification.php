<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Tests\Fixtures;

use Byrcsc\Whitelabel\Facades\Whitelabel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The same notification as {@see BrandedNotification}, without the queue, for
 * the tests that send synchronously.
 */
class PlainBrandNotification extends Notification
{
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
