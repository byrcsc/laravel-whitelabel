<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * A brand asset: either a path on a Laravel Storage disk, or an absolute URL.
 *
 * The package never checks that the file exists or that the disk is public.
 * Brand assets are expected to live on a publicly accessible disk.
 */
final readonly class BrandAsset
{
    public function __construct(
        public string $path,
        public ?string $disk = null,
    ) {}

    /**
     * Build an asset from a brand definition value.
     *
     * Accepts a plain string (a disk path or an absolute URL) or a
     * `['disk' => ..., 'path' => ...]` pair. An empty value means the brand
     * cleared the asset and yields null.
     *
     * @param  string|array{disk?: string|null, path?: string}  $value
     */
    public static function fromDefinition(string|array $value): ?self
    {
        if (is_string($value)) {
            return $value === '' ? null : new self($value);
        }

        $path = $value['path'] ?? '';

        if ($path === '') {
            return null;
        }

        $disk = $value['disk'] ?? null;

        return new self($path, $disk === '' ? null : $disk);
    }

    /**
     * Whether the path is already a URL and should be used untouched.
     */
    public function isAbsoluteUrl(): bool
    {
        return (bool) preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $this->path);
    }

    /**
     * A URL for this asset.
     *
     * An absolute URL passes through untouched. Anything else goes through
     * `Storage::disk(...)->url(...)`, on this asset's disk or the one named by
     * `whitelabel.assets.disk`. Nothing here checks that the file exists or
     * that the disk is public: that is the application's business, and the
     * check would cost a round trip on every page.
     */
    public function url(): string
    {
        if ($this->isAbsoluteUrl()) {
            return $this->path;
        }

        return Storage::disk($this->disk ?? self::defaultDisk())->url($this->path);
    }

    private static function defaultDisk(): ?string
    {
        $disk = Config::get('whitelabel.assets.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /**
     * @return array{disk: string|null, path: string}
     */
    public function toArray(): array
    {
        return ['disk' => $this->disk, 'path' => $this->path];
    }
}
