<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Resolvers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Answers with the brand named by `whitelabel.default`.
 *
 * Last in the chain, and the reason a console command or a queue worker with
 * no other context still has a brand. It answers with nothing when no default
 * is configured, or when the driver has no such brand yet.
 */
final class DefaultResolver implements BrandResolver
{
    public function __construct(
        private readonly Whitelabel $whitelabel,
        private readonly Config $config,
    ) {}

    public function resolve(): ?Brand
    {
        $id = $this->config->get('whitelabel.default');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return $this->whitelabel->find($id);
    }
}
