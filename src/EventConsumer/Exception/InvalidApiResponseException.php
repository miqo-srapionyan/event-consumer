<?php

declare(strict_types=1);

namespace App\EventConsumer\Exception;

class InvalidApiResponseException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid response format from API', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}