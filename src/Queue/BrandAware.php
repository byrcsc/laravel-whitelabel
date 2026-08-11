<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Queue;

/**
 * Makes a job, mailable, notification, or listener run with the brand that was
 * active when it was dispatched.
 *
 * ```php
 * class SendWelcomeEmail implements ShouldQueue
 * {
 *     use Queueable, BrandAware;
 * }
 * ```
 *
 * The brand's identifier is written into the queue payload at dispatch and the
 * brand is activated again in the worker, before the job runs. Nothing else
 * about the job changes, and jobs without the trait are untouched.
 *
 * You do not need this with `spatie/laravel-multitenancy`: a tenant-aware job
 * already restores its tenant, and the switch task activates that tenant's
 * brand. Use it for work that is branded but not tenant-scoped, and for
 * applications that do not use Spatie at all. Where both apply, the captured
 * brand wins — the same order the resolver chain uses.
 *
 * If no brand was active at dispatch, the job resolves its brand normally. If
 * the captured brand no longer exists when the job runs, the job fails with
 * {@see \Byrcsc\Whitelabel\Exceptions\CapturedBrandMissing} rather than
 * quietly rendering the default brand.
 */
trait BrandAware
{
    //
}
