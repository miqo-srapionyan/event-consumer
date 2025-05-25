<?php

declare(strict_types=1);

namespace App\EventConsumer\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiRequestException extends HttpException
{
    public function __construct(int $statusCode, string $url = '', string $message = '')
    {
        $message = $message ?: "API returned error status code: $statusCode";
        if ($url) {
            $message .= " for URL: $url";
        }

        parent::__construct($statusCode, $message);
    }
}