<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;

/**
 * The brand definition schema, shared by every driver.
 *
 * Drivers hand raw arrays to {@see self::validate()} and store what comes
 * back. {@see self::inherit()} implements the per-key fallback to the default
 * brand. A driver of your own gets the same validation and the same error
 * messages by calling `BrandDefinition::validate()` before it stores anything.
 */
final class BrandDefinition
{
    public const NAME = 'name';

    public const DOMAIN = 'domain';

    public const LOGO = 'logo';

    public const FAVICON = 'favicon';

    public const COLORS = 'colors';

    public const MAIL = 'mail';

    public const MAIL_FROM_NAME = 'from_name';

    public const MAIL_FROM_ADDRESS = 'from_address';

    public const SETTINGS = 'settings';

    /**
     * Every key a brand definition may set.
     *
     * @var list<string>
     */
    public const KEYS = [
        self::NAME,
        self::DOMAIN,
        self::LOGO,
        self::FAVICON,
        self::COLORS,
        self::MAIL,
        self::SETTINGS,
    ];

    /**
     * Keys that never fall back to the default brand.
     *
     * The domain identifies which brand a request belongs to. Inheriting it
     * would make every brand claim the default brand's host.
     *
     * @var list<string>
     */
    public const NON_INHERITED = [self::DOMAIN];

    /**
     * Validate a raw definition and return it normalised.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, mixed>
     *
     * @throws InvalidBrandDefinition
     */
    public static function validate(string $brandId, array $definition): array
    {
        $normalised = [];

        foreach ($definition as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::KEYS, true)) {
                throw InvalidBrandDefinition::unknownKey($brandId, (string) $key, self::KEYS);
            }

            self::rejectNull($brandId, $key, $value);

            $normalised[$key] = match ($key) {
                self::NAME => self::validateString($brandId, $key, $value),
                self::DOMAIN => self::validateDomain($brandId, $value),
                self::LOGO, self::FAVICON => self::validateAsset($brandId, $key, $value),
                self::COLORS => self::validateColors($brandId, $value),
                self::MAIL => self::validateMail($brandId, $value),
                self::SETTINGS => self::validateSettings($brandId, $value),
            };
        }

        return $normalised;
    }

    /**
     * Merge a brand's own definition over the default brand's, key by key.
     *
     * Nested maps such as `colors`, `mail`, and `settings` merge one key at a
     * time, so a brand that names a single colour keeps the rest. Any other
     * value the brand sets wins outright, including an empty string and an
     * empty list, which is how a brand clears an inherited value.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $own
     * @return array<string, mixed>
     */
    public static function inherit(array $base, array $own): array
    {
        foreach (self::NON_INHERITED as $key) {
            unset($base[$key]);
        }

        foreach ($own as $key => $value) {
            $base[$key] = self::combine($base[$key] ?? null, $value);
        }

        return $base;
    }

    /**
     * One key's worth of inheritance: two maps merge, everything else replaces.
     *
     * A list always replaces, so `'settings' => ['tags' => []]` clears an
     * inherited list. An empty array on its own counts as a map, so
     * `'colors' => []` changes nothing — clear individual colours instead.
     */
    private static function combine(mixed $inherited, mixed $value): mixed
    {
        if (! is_array($value) || ! is_array($inherited) || ! self::isMap($value) || ! self::isMap($inherited)) {
            return $value;
        }

        foreach ($value as $key => $nested) {
            $inherited[$key] = self::combine($inherited[$key] ?? null, $nested);
        }

        return $inherited;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function isMap(array $value): bool
    {
        return ! array_is_list($value) || $value === [];
    }

    private static function rejectNull(string $brandId, string $path, mixed $value): void
    {
        if ($value === null) {
            throw InvalidBrandDefinition::explicitNull($brandId, $path);
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            self::rejectNull($brandId, $path.'.'.$key, $nested);
        }
    }

    private static function validateString(string $brandId, string $path, mixed $value): string
    {
        if (! is_string($value)) {
            throw InvalidBrandDefinition::at($brandId, $path, 'must be a string, '.get_debug_type($value).' given');
        }

        return $value;
    }

    private static function validateDomain(string $brandId, mixed $value): string
    {
        $domain = self::validateString($brandId, self::DOMAIN, $value);

        if (str_contains($domain, '/')) {
            throw InvalidBrandDefinition::at(
                $brandId,
                self::DOMAIN,
                'must be a bare host such as [app.acme.com], without a scheme or a path',
            );
        }

        return mb_strtolower($domain);
    }

    /**
     * @return string|array{disk?: string, path?: string}
     */
    private static function validateAsset(string $brandId, string $path, mixed $value): string|array
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw InvalidBrandDefinition::at(
                $brandId,
                $path,
                'must be a path string or a [disk, path] pair, '.get_debug_type($value).' given',
            );
        }

        $asset = [];

        foreach ($value as $key => $nested) {
            if ($key !== 'disk' && $key !== 'path') {
                throw InvalidBrandDefinition::unknownKey($brandId, $path.'.'.$key, ['disk', 'path']);
            }

            $asset[$key] = self::validateString($brandId, $path.'.'.$key, $nested);
        }

        // A pair may name only a disk: the path can arrive from the default
        // brand, since asset pairs inherit key by key like any other map.
        /** @var array{disk?: string, path?: string} $asset */
        return $asset;
    }

    /**
     * @return array<string, string>
     */
    private static function validateColors(string $brandId, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidBrandDefinition::at(
                $brandId,
                self::COLORS,
                'must be a map of colour name to value, '.get_debug_type($value).' given',
            );
        }

        $colors = [];

        foreach ($value as $name => $color) {
            if (! is_string($name) || $name === '') {
                throw InvalidBrandDefinition::at($brandId, self::COLORS, 'has a colour with no name');
            }

            $colors[$name] = self::validateString($brandId, self::COLORS.'.'.$name, $color);
        }

        return $colors;
    }

    /**
     * @return array<string, string>
     */
    private static function validateMail(string $brandId, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidBrandDefinition::at(
                $brandId,
                self::MAIL,
                'must be a map of sender fields, '.get_debug_type($value).' given',
            );
        }

        $mail = [];
        $known = [self::MAIL_FROM_NAME, self::MAIL_FROM_ADDRESS];

        foreach ($value as $key => $nested) {
            if (! is_string($key) || ! in_array($key, $known, true)) {
                throw InvalidBrandDefinition::unknownKey($brandId, self::MAIL.'.'.$key, $known);
            }

            $mail[$key] = self::validateString($brandId, self::MAIL.'.'.$key, $nested);
        }

        // Checked here rather than at send time. With whitelabel.mail
        // .override_from on, a malformed address takes down every message the
        // brand sends, and a queued one fails in a worker where nobody is
        // looking. An empty string still means cleared.
        if (($mail[self::MAIL_FROM_ADDRESS] ?? '') !== ''
            && filter_var($mail[self::MAIL_FROM_ADDRESS], FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidBrandDefinition::at(
                $brandId,
                self::MAIL.'.'.self::MAIL_FROM_ADDRESS,
                'must be an email address',
            );
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validateSettings(string $brandId, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidBrandDefinition::at(
                $brandId,
                self::SETTINGS,
                'must be an array, '.get_debug_type($value).' given',
            );
        }

        $settings = [];

        foreach ($value as $key => $nested) {
            if (! is_string($key) || $key === '') {
                throw InvalidBrandDefinition::at(
                    $brandId,
                    self::SETTINGS,
                    'must be keyed by name at the top level, so it can be read as [settings.your_key]',
                );
            }

            $settings[$key] = self::validateSettingsValue($brandId, self::SETTINGS.'.'.$key, $nested);
        }

        return $settings;
    }

    /**
     * @return array<array-key, mixed>|scalar
     */
    private static function validateSettingsValue(string $brandId, string $path, mixed $value): array|bool|float|int|string
    {
        if (is_array($value)) {
            $level = [];

            foreach ($value as $key => $nested) {
                $level[$key] = self::validateSettingsValue($brandId, $path.'.'.$key, $nested);
            }

            return $level;
        }

        if (! is_scalar($value)) {
            throw InvalidBrandDefinition::at(
                $brandId,
                $path,
                'must be a scalar or an array, '.get_debug_type($value).' given',
            );
        }

        return $value;
    }
}
