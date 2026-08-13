<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Database\Factories;

use Byrcsc\Whitelabel\Models\BrandRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BrandRecord>
 */
class BrandRecordFactory extends Factory
{
    protected $model = BrandRecord::class;

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->company());

        return [
            'identifier' => $slug,
            'name' => $this->faker->company(),
            'domain' => "{$slug}.test",
            'logo_disk' => 'public',
            'logo_path' => "brands/{$slug}/logo.svg",
            'favicon_disk' => 'public',
            'favicon_path' => "brands/{$slug}/favicon.ico",
            'colors' => [
                'primary' => $this->faker->hexColor(),
                'secondary' => $this->faker->hexColor(),
            ],
            'mail_from_name' => $this->faker->company(),
            'mail_from_address' => "hello@{$slug}.test",
            'settings' => [],
        ];
    }

    /**
     * A brand with nothing but an identifier, so every key falls back.
     */
    public function bare(): static
    {
        return $this->state(fn (): array => [
            'name' => null,
            'domain' => null,
            'logo_disk' => null,
            'logo_path' => null,
            'favicon_disk' => null,
            'favicon_path' => null,
            'colors' => null,
            'mail_from_name' => null,
            'mail_from_address' => null,
            'settings' => null,
        ]);
    }

    public function identifiedBy(string $identifier): static
    {
        return $this->state(fn (): array => [
            'identifier' => $identifier,
            'domain' => "{$identifier}.test",
        ]);
    }
}
