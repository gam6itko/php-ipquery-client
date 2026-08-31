<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\CachedIPQuery;
use Gam6itko\IPQuery\IPQueryStub;
use Gam6itko\IPQuery\LookupException;
use Gam6itko\IPQuery\LookupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
#[CoversClass(CachedIPQuery::class)]
final class CachedIPQueryTest extends TestCase
{
    public function testReturnsCachedValueWithoutCallingInner(): void
    {
        $cached = self::ipQueryResult('RU');

        $inner = $this->createMock(LookupInterface::class);
        $inner->expects(self::never())->method('lookup');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with(self::cacheKey('1.2.3.4'))->willReturn($cached);
        $cache->expects(self::never())->method('set');

        $sut = new CachedIPQuery($inner, $cache);

        self::assertSame($cached, $sut->lookup('1.2.3.4'));
    }

    public function testStoresResultOnCacheMiss(): void
    {
        $result = self::ipQueryResult('DE');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with(self::cacheKey('1.2.3.4'))->willReturn(null);
        $cache->expects(self::once())->method('set')->with(self::cacheKey('1.2.3.4'), $result);

        $sut = new CachedIPQuery(new IPQueryStub($result), $cache);

        self::assertSame($result, $sut->lookup('1.2.3.4'));
    }

    public function testUsesPsr16SafeKeyForIpv6(): void
    {
        $result = self::ipQueryResult('DE');
        $ipv6 = '2001:db8::1';

        $capturedKeys = [];
        $capture = static function (string $key) use (&$capturedKeys): bool {
            $capturedKeys[] = $key;

            return true;
        };

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with(self::callback($capture))->willReturn(null);
        $cache->expects(self::once())->method('set')->with(self::callback($capture), $result);

        $sut = new CachedIPQuery(new IPQueryStub($result), $cache);

        self::assertSame($result, $sut->lookup($ipv6));

        // PSR-16 reserves {}()/\@: — the colons in an IPv6 address must not leak into the key.
        foreach ($capturedKeys as $key) {
            self::assertSame($key, self::cacheKey($ipv6));
            self::assertDoesNotMatchRegularExpression('#[{}()/\\\\@:]#', $key);
            self::assertLessThanOrEqual(64, \strlen($key));
        }
    }

    private static function cacheKey(string $ip): string
    {
        return 'ipquery_'.\hash('xxh128', $ip);
    }

    public function testDoesNotCacheOnException(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects(self::never())->method('set');

        $sut = new CachedIPQuery(new IPQueryStub(new LookupException('geo is down')), $cache);

        $this->expectException(LookupException::class);

        $sut->lookup('1.2.3.4');
    }

    /**
     * @return TIPQueryResult
     */
    private static function ipQueryResult(string $countryCode): array
    {
        return [
            'ip'       => '1.2.3.4',
            'isp'      => ['asn' => '', 'org' => '', 'isp' => ''],
            'location' => [
                'country'      => '',
                'country_code' => $countryCode,
                'city'         => '',
                'state'        => '',
                'zipcode'      => '',
                'latitude'     => 0.0,
                'longitude'    => 0.0,
                'timezone'     => '',
                'localtime'    => '',
            ],
            'risk' => [
                'abuse_confidence_score'   => 0,
                'usage_type'               => '',
                'is_tor'                   => false,
                'total_reports'            => 0,
                'number_of_users_reported' => 0,
                'last_reported_at'         => '',
            ],
        ];
    }
}
