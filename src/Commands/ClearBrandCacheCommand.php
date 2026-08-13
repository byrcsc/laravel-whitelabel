<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Commands;

use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Drivers\CachedBrandRepository;
use Illuminate\Console\Command;

class ClearBrandCacheCommand extends Command
{
    protected $signature = 'whitelabel:clear';

    protected $description = 'Forget every cached brand, so the next lookup reads from the driver';

    public function handle(BrandRepository $brands): int
    {
        $brands->flush();

        if ($brands instanceof CachedBrandRepository) {
            $this->components->info('Brand cache cleared.');

            return self::SUCCESS;
        }

        // Saying "cleared" here would be a lie: the config driver holds its
        // brands for the length of one process, and caching may simply be off.
        $this->components->info(
            'The configured brand driver does not cache anything, so there was nothing to clear.'
        );

        return self::SUCCESS;
    }
}
