<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\View\Components;

use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Emits the brand's colour set as CSS custom properties.
 *
 * ```blade
 * <x-whitelabel::styles />
 * ```
 *
 * Renders `--brand-primary`, `--brand-secondary`, and whatever else the colour
 * set holds, ready for your own CSS or Tailwind to consume. The prefix comes
 * from `whitelabel.css.prefix` and can be overridden per usage. A brand with
 * no colours renders nothing at all, not an empty `<style>` block.
 */
class Styles extends Component
{
    public const DEFAULT_PREFIX = 'brand';

    /**
     * The declarations to write, already safe for a `<style>` element.
     *
     * @var array<string, string>
     */
    public array $variables;

    public function __construct(Whitelabel $whitelabel, Config $config, mixed $prefix = null)
    {
        if (! is_string($prefix)) {
            $configured = $config->get('whitelabel.css.prefix');
            $prefix = is_string($configured) ? $configured : self::DEFAULT_PREFIX;
        }

        $this->variables = self::variablesFor($whitelabel, $prefix);
    }

    public function shouldRender(): bool
    {
        return $this->variables !== [];
    }

    public function render(): View
    {
        return view('whitelabel::components.styles');
    }

    /**
     * The active brand's colours, named and cleaned.
     *
     * A `<style>` element is CSS raw text: the browser never HTML-decodes it,
     * so Blade's escaping cannot protect it and would only corrupt values that
     * legitimately contain `&` or a quote. Cleaning has to happen here instead.
     * Names that are not plain identifiers are dropped, and the characters that
     * would end a declaration, end the block, or start a comment are stripped
     * from values.
     *
     * Brand definitions are developer-supplied and trusted, per SECURITY.md.
     * This is the one place a brand value becomes markup rather than text, so
     * it is not trusted quite that far.
     *
     * @return array<string, string>
     */
    private static function variablesFor(Whitelabel $whitelabel, string $prefix): array
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) ?? '';

        $variables = [];

        foreach ($whitelabel->current()?->colors() ?? [] as $name => $value) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
                continue;
            }

            $value = trim(str_replace(['<', '>', '{', '}', ';', '/*', '*/', '\\'], '', $value));

            if ($value === '') {
                continue;
            }

            $variables['--'.($prefix === '' ? '' : $prefix.'-').$name] = $value;
        }

        return $variables;
    }
}
