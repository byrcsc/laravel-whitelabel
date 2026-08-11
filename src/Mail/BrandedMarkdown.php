<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Mail;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * The brand values Laravel's markdown mail components need.
 *
 * Laravel's markdown theme is a static CSS file, chosen once by
 * `mail.markdown.theme`, so it cannot carry a brand's colours. Mail clients
 * also ignore CSS custom properties, which is how the web side does it. So the
 * branded components write plain inline styles instead, and this is where they
 * get their values. Every method answers with nothing when there is no brand,
 * or no such value on it, and the component then renders exactly what Laravel
 * would have rendered.
 *
 * @internal
 */
final class BrandedMarkdown
{
    /**
     * The brand logo, as a URL a mail client can actually fetch.
     */
    public static function logoUrl(): ?string
    {
        $url = self::brand()?->logoUrl();

        if ($url === null || $url === '') {
            return null;
        }

        // Storage disks commonly return a root-relative path. That resolves to
        // nothing in a mail client, which has no page to be relative to.
        return str_starts_with($url, '/') ? URL::to($url) : $url;
    }

    public static function name(): string
    {
        return self::brand()?->name() ?? '';
    }

    /**
     * Colour for the header text, when the brand has no logo to show instead.
     */
    public static function headingStyle(): string
    {
        $color = self::color('primary');

        return $color === null ? '' : " color: {$color};";
    }

    /**
     * Paint a markdown button in the brand's colour.
     *
     * Only the primary button: `success` and `error` mean green and red to a
     * reader, and repainting them in the brand's colour would lose that.
     */
    public static function buttonStyle(string $color): string
    {
        if ($color !== 'primary') {
            return '';
        }

        $brandColor = self::color('primary');

        return $brandColor === null
            ? ''
            : "background-color: {$brandColor}; border-color: {$brandColor};";
    }

    /**
     * A colour, with anything that could escape an inline style attribute
     * removed. Brand definitions are trusted, but this one reaches markup.
     */
    private static function color(string $name): ?string
    {
        $color = self::brand()?->color($name);

        if ($color === null) {
            return null;
        }

        $color = trim(str_replace(['<', '>', '"', "'", ';', '/*', '*/', '\\'], '', $color));

        return $color === '' ? null : $color;
    }

    /**
     * The active brand, or nothing when markdown branding is switched off.
     *
     * The flag is read here rather than at boot so that turning it off takes
     * effect immediately and is testable without rebooting the application.
     * With it off every accessor answers with nothing and the components
     * render exactly what Laravel's do.
     */
    private static function brand(): ?Brand
    {
        if (Config::get('whitelabel.mail.markdown') === false) {
            return null;
        }

        return app(Whitelabel::class)->current();
    }
}
