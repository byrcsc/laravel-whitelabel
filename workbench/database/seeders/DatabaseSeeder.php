<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Byrcsc\Whitelabel\BrandRepositoryManager;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Illuminate\Database\Seeder;
use Workbench\App\Models\Tenant;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTenants();
        $this->seedStoredBrands();
    }

    /**
     * A tenant carrying its brand in a JSON column.
     */
    private function seedTenants(): void
    {
        Tenant::query()->updateOrCreate(['slug' => 'initech'], [
            'name' => 'Initech',
            'brand' => [
                'name' => 'Initech',
                'colors' => ['primary' => '#dc2626'],
                'settings' => ['support_url' => 'https://support.initech.test'],
            ],
        ]);
    }

    /**
     * Brands created through the database driver's management API.
     *
     * The demo runs on the config driver, so the driver is asked for by name
     * here rather than through the contract, which would hand back the
     * configured one.
     */
    private function seedStoredBrands(): void
    {
        /** @var BrandRepository $brands */
        $brands = app(BrandRepositoryManager::class)->driver('database');

        foreach (['umbrella' => '#16a34a', 'soylent' => '#ca8a04'] as $id => $color) {
            if ($brands->has($id)) {
                $brands->delete($id);
            }

            $brands->create($id, [
                'name' => ucfirst($id),
                'domain' => "{$id}.localhost",
                'colors' => ['primary' => $color],
                'settings' => ['support_url' => "https://support.{$id}.test"],
            ]);
        }
    }
}
