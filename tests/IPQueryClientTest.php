<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\InvalidIpException;
use Gam6itko\IPQuery\IPQueryClient;
use Gam6itko\IPQuery\IPQueryRequestFactory;
use Gam6itko\IPQuery\LookupException;
use Gam6itko\IPQuery\Tests\Fixture\RecordingClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(IPQueryClient::class)]
final class IPQueryClientTest extends TestCase
{
    private ?RecordingClient $http = null;

    public function testReturnsDecodedPayloadOnSuccess(): void
    {
        $payload = self::payloadJson('RU');
        $client = $this->makeClient(200, $payload);

        $result = $client->lookup('8.8.8.8');

        self::assertSame('RU', $result['location']['country_code']);
        self::assertSame('8.8.8.8', $result['ip']);
        $this->assertRequested('GET', 'http://ip-query:8080/lookup/8.8.8.8');
    }

    #[DataProvider('dataValidIp')]
    public function testAcceptsValidIp(string $ip, string $expectedPath): void
    {
        $client = $this->makeClient(200, self::payloadJson('DE'));

        $result = $client->lookup($ip);

        self::assertSame('DE', $result['location']['country_code']);
        $this->assertRequested('GET', 'http://ip-query:8080'.$expectedPath);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dataValidIp(): iterable
    {
        yield 'ipv4'                  => ['8.8.8.8', '/lookup/8.8.8.8'];
        yield 'ipv4 boundary'         => ['255.255.255.255', '/lookup/255.255.255.255'];
        yield 'ipv4 zeros'            => ['0.0.0.0', '/lookup/0.0.0.0'];
        // IPv6 colons must be percent-encoded in the request path.
        yield 'ipv6'                  => ['2001:db8::1', '/lookup/2001%3Adb8%3A%3A1'];
        yield 'ipv6 full'             => ['2001:0db8:0000:0000:0000:0000:0000:0001', '/lookup/2001%3A0db8%3A0000%3A0000%3A0000%3A0000%3A0000%3A0001'];
        yield 'ipv6 loopback'         => ['::1', '/lookup/%3A%3A1'];
        yield 'ipv4-mapped ipv6'      => ['::ffff:192.0.2.1', '/lookup/%3A%3Affff%3A192.0.2.1'];
    }

    #[DataProvider('dataInvalidIp')]
    public function testRejectsInvalidIp(string $ip): void
    {
        $client = $this->makeClient(200, self::payloadJson('RU'));

        try {
            $client->lookup($ip);
            self::fail('InvalidIpException was not thrown');
        } catch (InvalidIpException $e) {
            self::assertInstanceOf(LookupException::class, $e);
            self::assertStringContainsString('Invalid IP address', $e->getMessage());
            self::assertStringContainsString($ip, $e->getMessage());
        }

        // No request must reach the geo service for an invalid IP.
        self::assertNull($this->http?->lastRequest);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dataInvalidIp(): iterable
    {
        yield 'empty'            => [''];
        yield 'whitespace'       => [' '];
        yield 'garbage'          => ['not-an-ip'];
        yield 'hostname'         => ['example.com'];
        yield 'partial'          => ['8.8.8'];
        yield 'trailing dot'     => ['8.8.8.8.'];
        yield 'leading space'    => [' 8.8.8.8'];
        yield 'trailing space'   => ['8.8.8.8 '];
        yield 'octet overflow'   => ['256.0.0.1'];
        yield 'out of range'     => ['999.999.999.999'];
        yield 'negative octet'   => ['-1.0.0.0'];
        yield 'cidr'             => ['8.8.8.0/24'];
        yield 'ipv4 with port'   => ['8.8.8.8:80'];
        yield 'url'              => ['http://8.8.8.8'];
        yield 'malformed ipv6'   => ['2001:db8:::1'];
        yield 'ipv6 with zone'   => ['fe80::1%eth0'];
    }

    public function testOwnReturnsDecodedPayload(): void
    {
        $client = $this->makeClient(200, self::payloadJson('DE'));

        $result = $client->own();

        self::assertSame('DE', $result['location']['country_code']);
        $this->assertRequested('GET', 'http://ip-query:8080/own/all');
    }

    public function testOwnIpReturnsPlainText(): void
    {
        $client = $this->makeClient(200, '203.0.113.7');

        self::assertSame('203.0.113.7', $client->ownIp());
        $this->assertRequested('GET', 'http://ip-query:8080/own');
    }

    public function testOwnIpTrimsWhitespaceAndQuotes(): void
    {
        $client = $this->makeClient(200, "\"203.0.113.7\"\n");

        self::assertSame('203.0.113.7', $client->ownIp());
    }

    public function testOwnIpThrowsOnEmptyBody(): void
    {
        $client = $this->makeClient(200, '');

        $this->expectException(LookupException::class);
        $this->expectExceptionMessage('IPQuery returned an empty own IP');

        $client->ownIp();
    }

    public function testThrowsOnNon200Status(): void
    {
        $client = $this->makeClient(503, '');

        try {
            $client->lookup('8.8.8.8');
            self::fail('LookupException was not thrown');
        } catch (LookupException $e) {
            self::assertStringContainsString('IPQuery returned status 503', $e->getMessage());
            // The HTTP status is exposed for programmatic handling.
            self::assertSame(503, $e->getStatusCode());
        }
    }

    public function testWrapsClientException(): void
    {
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class('network is down') extends \RuntimeException implements ClientExceptionInterface {};
            }
        };

        $client = new IPQueryClient($httpClient, $this->makeRequestFactory());

        $this->expectException(LookupException::class);
        $this->expectExceptionMessage('IPQuery request failed');

        $client->lookup('8.8.8.8');
    }

    public function testThrowsOnInvalidJson(): void
    {
        $client = $this->makeClient(200, '{not-json');

        $this->expectException(LookupException::class);
        $this->expectExceptionMessage('IPQuery returned invalid JSON');

        $client->lookup('8.8.8.8');
    }

    public function testThrowsOnUnexpectedPayload(): void
    {
        $client = $this->makeClient(200, '{"ip":"8.8.8.8"}');

        $this->expectException(LookupException::class);
        $this->expectExceptionMessage('IPQuery returned unexpected payload');

        $client->lookup('8.8.8.8');
    }

    private function makeClient(int $status, string $body): IPQueryClient
    {
        $psr17 = new Psr17Factory();
        $response = $psr17->createResponse($status)
            ->withBody($psr17->createStream($body));

        $this->http = new RecordingClient($response);

        return new IPQueryClient($this->http, $this->makeRequestFactory());
    }

    private function assertRequested(string $method, string $uri): void
    {
        self::assertNotNull($this->http, 'makeClient() was not called');
        $request = $this->http->lastRequest;
        self::assertNotNull($request, 'No request was sent');
        self::assertSame($method, $request->getMethod());
        self::assertSame($uri, (string) $request->getUri());
    }

    private function makeRequestFactory(): IPQueryRequestFactory
    {
        $psr17 = new Psr17Factory();

        return new IPQueryRequestFactory($psr17, $psr17, 'http://ip-query:8080');
    }

    private static function payloadJson(string $countryCode): string
    {
        return \json_encode([
            'ip'       => '8.8.8.8',
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
        ], \JSON_THROW_ON_ERROR);
    }
}
