<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\IPQueryClient;
use Gam6itko\IPQuery\IPQueryRequestFactory;
use Gam6itko\IPQuery\LookupException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(IPQueryClient::class)]
final class IPQueryClientTest extends TestCase
{
    public function testReturnsDecodedPayloadOnSuccess(): void
    {
        $payload = self::payloadJson('RU');
        $client = $this->makeClient(200, $payload);

        $result = $client->lookup('8.8.8.8');

        self::assertSame('RU', $result['location']['country_code']);
        self::assertSame('8.8.8.8', $result['ip']);
    }

    public function testOwnReturnsDecodedPayload(): void
    {
        $client = $this->makeClient(200, self::payloadJson('DE'));

        $result = $client->own();

        self::assertSame('DE', $result['location']['country_code']);
    }

    public function testOwnIpReturnsPlainText(): void
    {
        $client = $this->makeClient(200, '203.0.113.7');

        self::assertSame('203.0.113.7', $client->ownIp());
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

        $this->expectException(LookupException::class);
        $this->expectExceptionMessage('IPQuery returned status 503');

        $client->lookup('8.8.8.8');
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

        $httpClient = new class($response) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        return new IPQueryClient($httpClient, $this->makeRequestFactory());
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
