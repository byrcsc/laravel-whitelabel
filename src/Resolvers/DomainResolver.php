<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Resolvers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\Contracts\BrandResolver;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Answers with the brand whose domain matches the request host.
 *
 * Outside an HTTP request there is no host to match, so this resolver answers
 * with nothing rather than erroring, and rather than matching against the
 * placeholder host Laravel synthesises for console commands and queue workers.
 */
final class DomainResolver implements BrandResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly Whitelabel $whitelabel,
    ) {}

    public function resolve(): ?Brand
    {
        $host = $this->requestHost();

        return $host === null ? null : $this->whitelabel->findByDomain($host);
    }

    private function requestHost(): ?string
    {
        if (! $this->container->bound(Request::class)) {
            return null;
        }

        $request = $this->container->make(Request::class);

        // A request Laravel synthesised from CLI globals carries no Host
        // header, so this is what separates a real request from a stand-in.
        if (! $request->server->has('HTTP_HOST')) {
            return null;
        }

        $host = $request->getHost();

        return $host === '' ? null : $host;
    }
}
