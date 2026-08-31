<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Client for the self-hosted IPQuery geo service.
 *
 * `lookup()` accepts both IPv4 and IPv6 addresses (as the server does); input
 * that is not a valid IP address is rejected with {@see InvalidIpException}
 * before an HTTP request is made.
 *
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
final readonly class IPQueryClient implements LookupInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    /**
     * @return TIPQueryResult
     *
     * @throws InvalidIpException when $ip is not a valid IP address
     * @throws LookupException
     */
    #[\Override]
    public function lookup(string $ip): array
    {
        if (false === \filter_var($ip, \FILTER_VALIDATE_IP)) {
            throw InvalidIpException::forIp($ip);
        }

        return $this->fetchResult('/lookup/'.\rawurlencode($ip), \sprintf('for "%s"', $ip));
    }

    /**
     * Resolves geo data for the caller's own IP as seen by the geo service (`GET /own/all`).
     *
     * @return TIPQueryResult
     *
     * @throws LookupException
     */
    public function own(): array
    {
        return $this->fetchResult('/own/all', 'for own IP');
    }

    /**
     * Returns the caller's own IP address as seen by the geo service (`GET /own`).
     *
     * @return non-empty-string
     *
     * @throws LookupException
     */
    public function ownIp(): string
    {
        // The endpoint returns the IP as plain text, sometimes wrapped in quotes.
        $ip = \trim($this->fetchBody('/own', 'for own IP'), " \t\n\r\0\x0B\"");
        if ('' === $ip) {
            throw new LookupException('IPQuery returned an empty own IP');
        }

        return $ip;
    }

    /**
     * @return TIPQueryResult
     *
     * @throws LookupException
     */
    private function fetchResult(string $path, string $subject): array
    {
        $body = $this->fetchBody($path, $subject);

        try {
            $data = \json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LookupException(\sprintf('IPQuery returned invalid JSON %s: %s', $subject, $e->getMessage()), $e);
        }

        if (!\is_array($data) || !isset($data['location']) || !\is_array($data['location']) || !isset($data['location']['country_code'])) {
            throw new LookupException(\sprintf('IPQuery returned unexpected payload %s', $subject));
        }

        /** @var TIPQueryResult $result */
        $result = $data;

        return $result;
    }

    /**
     * @throws LookupException
     */
    private function fetchBody(string $path, string $subject): string
    {
        $request = $this->requestFactory->createRequest('GET', $path);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new LookupException(\sprintf('IPQuery request failed %s: %s', $subject, $e->getMessage()), $e);
        }

        $status = $response->getStatusCode();
        if (200 !== $status) {
            throw new LookupException(\sprintf('IPQuery returned status %d %s', $status, $subject), null, $status);
        }

        return (string) $response->getBody();
    }
}
