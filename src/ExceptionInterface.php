<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

/**
 * Marker interface implemented by every exception this library throws.
 *
 * Catch it to handle any failure originating from the client in one place:
 *
 * ```php
 * try {
 *     $client->lookup($ip);
 * } catch (\Gam6itko\IPQuery\ExceptionInterface $e) {
 *     // any IPQuery client failure
 * }
 * ```
 */
interface ExceptionInterface extends \Throwable
{
}
