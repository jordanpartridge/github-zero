<?php

namespace JordanPartridge\GitHubZero\Support;

class ComponentResult
{
    /**
     * Create a successful component result.
     */
    public static function success(array $data, array $metadata = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'metadata' => $metadata,
            'timestamp' => time(),
        ];
    }

    /**
     * Create an error component result.
     */
    public static function error(string $message, int $code = 1): array
    {
        return [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * Check if result represents success.
     */
    public static function isSuccess(array $result): bool
    {
        return $result['success'] ?? false;
    }

    /**
     * Get data from successful result.
     */
    public static function getData(array $result): array
    {
        return $result['data'] ?? [];
    }

    /**
     * Get error message from failed result.
     */
    public static function getError(array $result): string
    {
        return $result['error']['message'] ?? 'Unknown error';
    }

    /**
     * Get error code from failed result.
     */
    public static function getErrorCode(array $result): int
    {
        return $result['error']['code'] ?? 1;
    }
}
