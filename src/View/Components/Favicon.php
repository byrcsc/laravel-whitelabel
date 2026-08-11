<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\View\Components;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders the brand's favicon as a `link` tag.
 *
 * ```blade
 * <x-whitelabel::favicon />
 * ```
 *
 * The `type` is guessed from the file extension, and can be given explicitly.
 * Attributes are forwarded to the tag. A brand with no favicon, after
 * fallback, renders nothing.
 */
class Favicon extends Component
{
    public ?string $url;

    public ?string $type;

    /**
     * The handful of formats a browser will accept as an icon.
     */
    private const TYPES = [
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(Whitelabel $whitelabel, ?string $type = null)
    {
        $this->url = $whitelabel->current()?->faviconUrl();
        $this->type = $type ?? $this->guessType($this->url);
    }

    public function shouldRender(): bool
    {
        return $this->url !== null;
    }

    public function render(): View
    {
        return view('whitelabel::components.favicon');
    }

    private function guessType(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $extension = mb_strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));

        return self::TYPES[$extension] ?? null;
    }
}
