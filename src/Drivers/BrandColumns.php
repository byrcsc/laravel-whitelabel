<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Drivers;

use Byrcsc\Whitelabel\BrandDefinition;
use Byrcsc\Whitelabel\Models\BrandRecord;

/**
 * Translates between a brand definition and the columns of the brands table.
 *
 * NULL means the brand does not define the key, so it inherits from the
 * default brand. An empty string means the brand set the key to nothing, so it
 * is cleared. That is the same distinction the definition schema draws between
 * an absent key and an empty one, which is what lets a brand round-trip
 * through the table unchanged.
 *
 * @internal
 */
final class BrandColumns
{
    /**
     * @param  array<string, mixed>  $definition  A validated definition.
     * @return array<string, mixed>
     */
    public static function fromDefinition(array $definition): array
    {
        $mail = $definition[BrandDefinition::MAIL] ?? [];

        // A cleared domain and an absent domain are the same thing, because a
        // domain never inherits. Storing the cleared one as NULL keeps it out
        // of the unique index, where two brands clearing their domain would
        // otherwise collide with each other.
        $domain = $definition[BrandDefinition::DOMAIN] ?? null;

        return [
            BrandDefinition::NAME => $definition[BrandDefinition::NAME] ?? null,
            BrandDefinition::DOMAIN => $domain === '' ? null : $domain,
            ...self::assetColumns('logo', $definition[BrandDefinition::LOGO] ?? null),
            ...self::assetColumns('favicon', $definition[BrandDefinition::FAVICON] ?? null),
            BrandDefinition::COLORS => $definition[BrandDefinition::COLORS] ?? null,
            'mail_from_name' => is_array($mail) ? ($mail[BrandDefinition::MAIL_FROM_NAME] ?? null) : null,
            'mail_from_address' => is_array($mail) ? ($mail[BrandDefinition::MAIL_FROM_ADDRESS] ?? null) : null,
            BrandDefinition::SETTINGS => $definition[BrandDefinition::SETTINGS] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toDefinition(BrandRecord $record): array
    {
        $definition = [];

        if ($record->name !== null) {
            $definition[BrandDefinition::NAME] = $record->name;
        }

        if ($record->domain !== null) {
            $definition[BrandDefinition::DOMAIN] = $record->domain;
        }

        $logo = self::asset($record->logo_disk, $record->logo_path);

        if ($logo !== null) {
            $definition[BrandDefinition::LOGO] = $logo;
        }

        $favicon = self::asset($record->favicon_disk, $record->favicon_path);

        if ($favicon !== null) {
            $definition[BrandDefinition::FAVICON] = $favicon;
        }

        if ($record->colors !== null) {
            $definition[BrandDefinition::COLORS] = $record->colors;
        }

        $mail = array_filter(
            [
                BrandDefinition::MAIL_FROM_NAME => $record->mail_from_name,
                BrandDefinition::MAIL_FROM_ADDRESS => $record->mail_from_address,
            ],
            static fn (?string $value): bool => $value !== null,
        );

        if ($mail !== []) {
            $definition[BrandDefinition::MAIL] = $mail;
        }

        if ($record->settings !== null) {
            $definition[BrandDefinition::SETTINGS] = $record->settings;
        }

        return $definition;
    }

    /**
     * @return array<string, string|null>
     */
    private static function assetColumns(string $prefix, mixed $value): array
    {
        $columns = ["{$prefix}_disk" => null, "{$prefix}_path" => null];

        if (is_string($value)) {
            $columns["{$prefix}_path"] = $value;

            return $columns;
        }

        if (! is_array($value)) {
            return $columns;
        }

        foreach (['disk', 'path'] as $key) {
            if (isset($value[$key]) && is_string($value[$key])) {
                $columns["{$prefix}_{$key}"] = $value[$key];
            }
        }

        return $columns;
    }

    /**
     * Rebuild the definition value an asset's two columns came from.
     *
     * A path with no disk goes back as the plain string it was written as.
     * That matters beyond tidiness: a string replaces an inherited asset
     * outright, where a `['path' => ...]` map would merge with it and pick up
     * the default brand's disk.
     *
     * @return string|array{disk: string, path?: string}|null
     */
    private static function asset(?string $disk, ?string $path): string|array|null
    {
        if ($disk === null) {
            return $path;
        }

        return $path === null ? ['disk' => $disk] : ['disk' => $disk, 'path' => $path];
    }
}
