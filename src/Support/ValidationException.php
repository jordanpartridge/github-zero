<?php

namespace JordanPartridge\GitHubZero\Support;

use Exception;

class ValidationException extends Exception
{
    public function __construct(string $message = 'Validation failed', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
