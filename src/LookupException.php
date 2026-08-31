<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

final class LookupException extends \RuntimeException
{
    /**
     * @param int|null $statusCode HTTP status code, when the failure was an unexpected response status
     */
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        private readonly ?int $statusCode = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The HTTP status code that caused the failure, or null for transport/parsing failures.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
