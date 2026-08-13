<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Models;

use Byrcsc\Whitelabel\Database\Factories\BrandRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the database driver's brands table.
 *
 * This model is internal. It is never returned from the public API and it is
 * not covered by the package's versioning promise: the driver hydrates
 * {@see \Byrcsc\Whitelabel\Brand} objects from it, and that is what callers
 * see. It is documented only so the shipped factory is usable from your own
 * tests.
 *
 * @property int $id
 * @property string $identifier
 * @property string|null $name
 * @property string|null $domain
 * @property string|null $logo_disk
 * @property string|null $logo_path
 * @property string|null $favicon_disk
 * @property string|null $favicon_path
 * @property array<string, string>|null $colors
 * @property string|null $mail_from_name
 * @property string|null $mail_from_address
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static BrandRecordFactory factory(...$parameters)
 */
class BrandRecord extends Model
{
    /** @use HasFactory<BrandRecordFactory> */
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        $table = $this->config('table');

        return $table ?? 'brands';
    }

    public function getConnectionName(): ?string
    {
        return $this->config('connection');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'settings' => 'array',
        ];
    }

    protected static function newFactory(): BrandRecordFactory
    {
        return BrandRecordFactory::new();
    }

    private function config(string $key): ?string
    {
        /** @var mixed $value */
        $value = config("whitelabel.database.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
