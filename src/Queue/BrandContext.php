<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Queue;

use Byrcsc\Whitelabel\Exceptions\CapturedBrandMissing;
use Byrcsc\Whitelabel\Whitelabel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Carries the active brand from a dispatch into the worker that runs the job.
 *
 * Capture writes the brand's identifier into the queue payload, next to the
 * job rather than inside it, so nothing has to be serialised into the job
 * object and no trait has to fight `SerializesModels` over `__serialize()`.
 *
 * Restore is opt-in: the identifier rides along on every payload, but only a
 * job whose class uses {@see BrandAware} has it activated again.
 *
 * @internal
 */
final class BrandContext
{
    /**
     * The payload key holding the brand that was active at dispatch.
     */
    public const PAYLOAD_KEY = 'whitelabel_brand';

    public function __construct(private readonly Container $container) {}

    /**
     * Stamp every outgoing payload with the brand that is active right now.
     */
    public function capture(): void
    {
        Queue::createPayloadUsing(function (): array {
            try {
                $id = $this->whitelabel()->current()?->id();
            } catch (Throwable) {
                // Every dispatch in the application goes through here,
                // including ones from other packages and ones made before the
                // brands table exists. Not knowing the brand is a reason to
                // skip the stamp, never a reason to break someone else's job.
                return [];
            }

            return $id === null ? [] : [self::PAYLOAD_KEY => $id];
        });
    }

    /**
     * Activate the captured brand, for jobs that asked for it.
     *
     * @throws CapturedBrandMissing when the captured brand has since gone.
     */
    public function restore(JobProcessing $event): void
    {
        $payload = $event->job->payload();

        $id = $payload[self::PAYLOAD_KEY] ?? null;

        if (! is_string($id) || ! self::wantsBrandContext($payload)) {
            return;
        }

        $brand = $this->whitelabel()->find($id);

        if ($brand === null) {
            throw CapturedBrandMissing::named($id, $event->job->resolveName());
        }

        $this->whitelabel()->activate($brand);
    }

    /**
     * Whether the queued class opted in.
     *
     * Two names are checked, because neither is enough alone. `commandName` is
     * the pushed job, which for a queued mailable, notification, or listener is
     * one of Laravel's wrappers rather than the user's class. `displayName` is
     * the user's class in those cases — but it is a method a job may override
     * to give itself a human label, and then it names no class at all.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private static function wantsBrandContext(array $payload): bool
    {
        $data = $payload['data'] ?? [];

        $names = [
            $payload['displayName'] ?? null,
            is_array($data) ? ($data['commandName'] ?? null) : null,
        ];

        foreach ($names as $name) {
            if (is_string($name) && class_exists($name)
                && in_array(BrandAware::class, class_uses_recursive($name), true)) {
                return true;
            }
        }

        return false;
    }

    private function whitelabel(): Whitelabel
    {
        return $this->container->make(Whitelabel::class);
    }
}
