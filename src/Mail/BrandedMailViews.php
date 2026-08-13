<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Mail;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Mail\Markdown;

/**
 * Slots the package's markdown mail components in behind the application's own.
 *
 * `Markdown` captures its component paths in its constructor, from
 * `mail.markdown.paths`, and it is a singleton — so appending to that config is
 * only reliable while nothing has resolved it yet. This applies the path to the
 * instance instead, which works whenever the provider happens to boot.
 *
 * The package's components land after the application's published
 * `resources/views/vendor/mail` and before Laravel's own, which is the
 * precedence wanted: an application that published the mail views has said what
 * it wants, and Laravel's are the unbranded fallback.
 *
 * @internal
 */
final class BrandedMailViews
{
    public function __construct(private readonly Config $config) {}

    public function applyTo(Markdown $markdown, string $path): void
    {
        /** @var mixed $configured */
        $configured = $this->config->get('mail.markdown.paths', []);

        $paths = [];

        foreach (is_array($configured) ? $configured : [] as $configuredPath) {
            if (is_string($configuredPath)) {
                $paths[] = $configuredPath;
            }
        }

        $paths[] = $path;

        $markdown->loadComponentsFrom(array_values(array_unique($paths)));
    }
}
