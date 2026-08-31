<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\IPQueryStub;
use Gam6itko\IPQuery\LookupException;
use Gam6itko\IPQuery\LookupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
#[CoversClass(IPQueryStub::class)]
final class IPQueryStubTest extends TestCase
{
    public function testReturnsGivenResult(): void
    {
        $result = self::ipQueryResult('RU');

        self::assertSame($result, (new IPQueryStub($result))->lookup('1.2.3.4'));
    }

    public function testThrowsGivenThrowable(): void
    {
        $exception = new LookupException('geo is down');

        $this->expectExceptionObject($exception);

        (new IPQueryStub($exception))->lookup('1.2.3.4');
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
