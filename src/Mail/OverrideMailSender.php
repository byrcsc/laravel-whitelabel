<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Mail;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Sends mail from the active brand's address, when asked to.
 *
 * Off by default, and deliberately so: sending from a domain you have not set
 * SPF, DKIM, and DMARC records for is how mail stops arriving. Turn it on with
 * `whitelabel.mail.override_from` once each brand's domain is verified with
 * your provider.
 *
 * @internal
 */
final class OverrideMailSender
{
    public function __construct(
        private readonly Whitelabel $whitelabel,
        private readonly Config $config,
    ) {}

    public function handle(MessageSending $event): void
    {
        if ($this->config->get('whitelabel.mail.override_from') !== true) {
            return;
        }

        $brand = $this->whitelabel->current();

        if ($brand === null) {
            return;
        }

        $address = $brand->mailFromAddress();

        if ($address === null) {
            return;
        }

        $event->message->from(new Address($address, $brand->mailFromName() ?? ''));
    }
}
