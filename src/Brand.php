<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

/**
 * The complete visual and verbal identity the application wears for one
 * audience.
 *
 * A brand is immutable. Every driver hydrates this same type, and mutation
 * happens through a repository, never on the object.
 *
 * A brand only overrides what it defines. Any key it leaves out falls back to
 * the default brand it was hydrated against, key by key. A key it sets to an
 * empty value is cleared and does not fall back.
 *
 * @implements Arrayable<string, mixed>
 */
final class Brand implements Arrayable
{
    /**
     * The effective definition, with the fallback brand merged in.
     *
     * @var array<string, mixed>|null
     */
    private ?array $effective = null;

    /**
     * The brand's own definition, validated and normalised.
     *
     * @var array<string, mixed>
     */
    private readonly array $definition;

    /**
     * @param  array<array-key, mixed>  $definition  A brand definition, in the shape {@see BrandDefinition} describes.
     * @param  self|null  $fallback  The default brand this one falls back to.
     *
     * @throws InvalidBrandDefinition
     */
    public function __construct(
        public readonly string $id,
        array $definition,
        private readonly ?self $fallback = null,
    ) {
        $this->definition = BrandDefinition::validate($id, $definition);
    }

    /**
     * Read any core field or settings value by dot notation.
     *
     * `$brand->get('name')`, `$brand->get('colors.primary')` and
     * `$brand->get('settings.support_url')` all go through here.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'id') {
            return $this->id;
        }

        return Arr::get($this->effective(), $key, $default);
    }

    /**
     * Whether the key resolves to a value, on this brand or on its fallback.
     */
    public function has(string $key): bool
    {
        return $key === 'id' || Arr::has($this->effective(), $key);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): ?string
    {
        $name = $this->get(BrandDefinition::NAME);

        return is_string($name) ? $name : null;
    }

    /**
     * The domain this brand answers on.
     *
     * Unlike every other field, the domain never falls back to the default
     * brand: it identifies which brand a request belongs to, so inheriting it
     * would make two brands claim the same host.
     */
    public function domain(): ?string
    {
        $domain = $this->definition[BrandDefinition::DOMAIN] ?? null;

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    public function logo(): ?BrandAsset
    {
        return $this->asset(BrandDefinition::LOGO);
    }

    public function favicon(): ?BrandAsset
    {
        return $this->asset(BrandDefinition::FAVICON);
    }

    /**
     * Any asset-shaped value, including ones stored in the settings bag.
     *
     * `$brand->asset('settings.og_image')` reaches an asset the fixed core
     * does not name.
     */
    public function asset(string $key): ?BrandAsset
    {
        $value = $this->get($key);

        if (! is_string($value) && ! is_array($value)) {
            return null;
        }

        /** @var string|array{disk?: string|null, path?: string} $value */
        return BrandAsset::fromDefinition($value);
    }

    /**
     * @return array<string, string>
     */
    public function colors(): array
    {
        /** @var array<string, string> $colors */
        $colors = $this->get(BrandDefinition::COLORS, []);

        return $colors;
    }

    public function color(string $name, ?string $default = null): ?string
    {
        return $this->colors()[$name] ?? $default;
    }

    public function mailFromName(): ?string
    {
        return $this->nonEmptyString(BrandDefinition::MAIL.'.'.BrandDefinition::MAIL_FROM_NAME);
    }

    public function mailFromAddress(): ?string
    {
        return $this->nonEmptyString(BrandDefinition::MAIL.'.'.BrandDefinition::MAIL_FROM_ADDRESS);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = $this->get(BrandDefinition::SETTINGS, []);

        return $settings;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->get(BrandDefinition::SETTINGS.'.'.$key, $default);
    }

    /**
     * This brand's own definition, without the fallback merged in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->definition;
    }

    /**
     * The default brand this one falls back to, if it has one.
     */
    public function fallback(): ?self
    {
        return $this->fallback;
    }

    /**
     * The same brand resolved against a different default brand.
     */
    public function withFallback(?self $fallback): self
    {
        return new self($this->id, $this->definition, $fallback);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id] + $this->effective();
    }

    /**
     * @return array<string, mixed>
     */
    private function effective(): array
    {
        return $this->effective ??= $this->fallback === null
            ? $this->definition
            : BrandDefinition::inherit($this->fallback->effective(), $this->definition);
    }

    private function nonEmptyString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
