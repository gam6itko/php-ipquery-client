<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

/**
 * Thrown when a caller passes a string that is not a valid IP address.
 *
 * Both IPv4 and IPv6 are accepted; anything else is rejected before any HTTP
 * request is made.
 */
final class InvalidIpException extends LookupException
{
    public static function forIp(string $ip): self
    {
        return new self(\sprintf('Invalid IP address "%s"', $ip));
    }
}
