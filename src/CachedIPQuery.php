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
final class CachedIPQuery implements LookupInterface
{
    public function __construct(
        private readonly LookupInterface $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return TIPQueryResult
     *
     * @throws LookupException
     */
    public function lookup(string $ip): array
    {
        $key = self::cacheKey($ip);

        /** @var ?TIPQueryResult $cached */
        $cached = $this->cache->get($key);
        if (null !== $cached) {
            return $cached;
        }

        // A LookupException is not cached: otherwise a single geo-service outage
        // would stick around for the whole TTL.
        $result = $this->inner->lookup($ip);

        $this->cache->set($key, $result);

        return $result;
    }

    /**
     * Derives a PSR-16-safe cache key from an IP address.
     *
     * A raw IP cannot be used directly: PSR-16 reserves `{}()/\@:` in keys, so an
     * IPv6 address (which contains colons) would be an invalid key. Hashing also
     * keeps the key within the 64-character limit the spec guarantees.
     */
    private static function cacheKey(string $ip): string
    {
        return 'ipquery_'.\hash('xxh128', $ip);
    }
}
