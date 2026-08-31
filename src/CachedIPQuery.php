<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

use Psr\SimpleCache\CacheInterface;

/**
 * Caches geo-service responses: the IP-to-country mapping changes rarely,
 * while without a cache every call hits the geo service over live HTTP.
 *
 * The value depends solely on the IP, so no invalidation is needed — a TTL is enough.
 * The default TTL comes from the injected $cache implementation.
 *
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
final readonly class CachedIPQuery implements LookupInterface
{
    public function __construct(
        private LookupInterface $inner,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return TIPQueryResult
     *
     * @throws LookupException
     */
    #[\Override]
    public function lookup(string $ip): array
    {
        /** @var ?TIPQueryResult $cached */
        $cached = $this->cache->get($ip);
        if (null !== $cached) {
            return $cached;
        }

        // A LookupException is not cached: otherwise a single geo-service outage
        // would stick around for the whole TTL.
        $result = $this->inner->lookup($ip);

        $this->cache->set($ip, $result);

        return $result;
    }
}
