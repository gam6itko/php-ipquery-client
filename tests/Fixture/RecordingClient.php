<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests\Fixture;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 test double that records the last request it was asked to send
 * and always returns the pre-configured response.
 */
final class RecordingClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}
