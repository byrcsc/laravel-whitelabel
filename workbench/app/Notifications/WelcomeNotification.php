<?php

declare(strict_types=1);

namespace Workbench\App\Notifications;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Facades\Whitelabel;
use Byrcsc\Whitelabel\Queue\BrandAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A queued, branded welcome mail.
 *
 * `BrandAware` is what carries the brand from the request that queued this
 * into the worker that sends it. Take the trait off and the mail arrives with
 * the default brand instead — which is the demo's point.
 */
class WelcomeNotification extends Notification implements ShouldQueue
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
        $brand = Whitelabel::current();

        return (new MailMessage)
            ->subject('Welcome to '.($brand?->name() ?? 'our application'))
            ->greeting('Hello')
            ->line('This mail was queued by '.($brand?->name() ?? 'nobody in particular').'.')
            ->action('Visit support', $this->supportUrl($brand))
            ->line('The header logo and the button colour come from the brand.');
    }

    private function supportUrl(?Brand $brand): string
    {
        $url = $brand?->setting('support_url');

        return is_string($url) && $url !== '' ? $url : url('/');
    }
}
