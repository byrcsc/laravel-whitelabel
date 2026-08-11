<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\View\Components;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders the brand's logo as an `img` tag.
 *
 * ```blade
 * <x-whitelabel::logo class="h-8" />
 * ```
 *
 * Attributes are forwarded to the tag. The `alt` text defaults to the brand's
 * name. A brand with no logo, after fallback, renders nothing rather than a
 * broken image.
 */
class Logo extends Component
{
    public ?string $url;

    public string $alt;

    public function __construct(Whitelabel $whitelabel, ?string $alt = null)
    {
        $brand = $whitelabel->current();

        $this->url = $brand?->logoUrl();
        $this->alt = $alt ?? $brand?->name() ?? '';
    }

    public function shouldRender(): bool
    {
        return $this->url !== null;
    }

    public function render(): View
    {
        return view('whitelabel::components.logo');
    }
}
