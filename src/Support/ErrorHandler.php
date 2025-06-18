<?php

namespace JordanPartridge\GitHubZero\Support;

use Symfony\Component\Console\Output\OutputInterface;

class ErrorHandler
{
    /**
     * Standard error codes for GitHub Zero components.
     */
    public const ERROR_TOKEN_MISSING = 401;

    public const ERROR_VALIDATION_FAILED = 422;

    public const ERROR_API_ERROR = 500;

    public const ERROR_NETWORK_ERROR = 503;

    public const ERROR_NOT_FOUND = 404;

    public const ERROR_PERMISSION_DENIED = 403;

    public const ERROR_UNKNOWN = 1;

    /**
     * Handle and format errors consistently across components.
     */
    public static function handle(\Exception $e, OutputInterface $output): int
    {
        $errorCode = self::mapExceptionToCode($e);
        $message = self::formatErrorMessage($e);

        $output->writeln("<error>❌ {$message}</error>");

        // Provide additional context based on error type
        $suggestion = self::getErrorSuggestion($errorCode);
        if ($suggestion) {
            $output->writeln("<comment>💡 {$suggestion}</comment>");
        }

        return $errorCode;
    }

    /**
     * Map exception to standardized error code.
     */
    private static function mapExceptionToCode(\Exception $e): int
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'token') || str_contains($message, 'authentication')) {
            return self::ERROR_TOKEN_MISSING;
        }

        if (str_contains($message, 'validation') || $e instanceof ValidationException) {
            return self::ERROR_VALIDATION_FAILED;
        }

        if (str_contains($message, 'not found') || str_contains($message, '404')) {
            return self::ERROR_NOT_FOUND;
        }

        if (str_contains($message, 'permission') || str_contains($message, '403')) {
            return self::ERROR_PERMISSION_DENIED;
        }

        if (str_contains($message, 'network') || str_contains($message, 'connection')) {
            return self::ERROR_NETWORK_ERROR;
        }

        if (str_contains($message, 'api') || str_contains($message, 'github')) {
            return self::ERROR_API_ERROR;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * Format error message for display.
     */
    private static function formatErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // Clean up common API error messages
        $message = str_replace('GitHub API Error: ', '', $message);
        $message = str_replace('Error: ', '', $message);

        return ucfirst($message);
    }

    /**
     * Get helpful suggestion based on error code.
     */
    private static function getErrorSuggestion(int $errorCode): ?string
    {
        return match ($errorCode) {
            self::ERROR_TOKEN_MISSING => 'Set GITHUB_TOKEN environment variable with a valid GitHub personal access token',
            self::ERROR_VALIDATION_FAILED => 'Check your input parameters and try again',
            self::ERROR_NOT_FOUND => 'Verify the repository name and your access permissions',
            self::ERROR_PERMISSION_DENIED => 'Check your GitHub token permissions or repository access',
            self::ERROR_NETWORK_ERROR => 'Check your internet connection and try again',
            self::ERROR_API_ERROR => 'Check GitHub status at https://status.github.com',
            default => null,
        };
    }

    /**
     * Create a standardized error result array.
     */
    public static function createErrorResult(\Exception $e): array
    {
        return ComponentResult::error(
            self::formatErrorMessage($e),
            self::mapExceptionToCode($e)
        );
    }
}
