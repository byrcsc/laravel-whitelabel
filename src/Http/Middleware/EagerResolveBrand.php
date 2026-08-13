<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Http\Middleware;

use Byrcsc\Whitelabel\Whitelabel;
use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the brand at the start of the request and shares it with views.
 *
 * Optional. The package resolves lazily on first access without it. Add it to
 * a route group when you would rather a resolution failure surface at the
 * start of the request than halfway through rendering, or when you want
 * `$brand` available in every view without calling the helper.
 */
class EagerResolveBrand
{
    public function __construct(
        private readonly Whitelabel $whitelabel,
        private readonly ViewFactory $views,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->views->share('brand', $this->whitelabel->current());

        return $next($request);
    }
}
