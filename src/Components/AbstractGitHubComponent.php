<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Components;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GitHubZero\Contracts\GitHubComponent;
use JordanPartridge\GitHubZero\Support\ComponentResult;
use JordanPartridge\GitHubZero\Support\ErrorHandler;
use JordanPartridge\GitHubZero\Support\ValidationException;

abstract class AbstractGitHubComponent implements GitHubComponent
{
    protected Github $github;

    public function __construct(Github $github)
    {
        $this->github = $github;
    }

    /**
     * Execute the component with validation.
     */
    public function execute(array $params = []): array
    {
        if (! $this->validate($params)) {
            throw new ValidationException('Invalid parameters provided to component');
        }

        try {
            $result = $this->executeComponent($params);

            return ComponentResult::success($result, $this->getMetadata());
        } catch (\Exception $e) {
            return ErrorHandler::createErrorResult($e);
        }
    }

    /**
     * Validate parameters against schema.
     */
    public function validate(array $params): bool
    {
        $schema = $this->getSchema();

        // Check required parameters
        foreach ($schema['required'] ?? [] as $field) {
            if (! isset($params[$field])) {
                return false;
            }
        }

        // Validate parameter types and constraints
        foreach ($schema['properties'] ?? [] as $field => $definition) {
            if (isset($params[$field])) {
                if (! $this->validateField($params[$field], $definition)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get supported output formats.
     */
    public function getSupportedFormats(): array
    {
        return ['json', 'array'];
    }

    /**
     * Check if GitHub token is available.
     */
    protected function hasGitHubToken(): bool
    {
        return ! empty($_ENV['GITHUB_TOKEN']) || ! empty(getenv('GITHUB_TOKEN'));
    }

    /**
     * Validate individual field against definition.
     */
    private function validateField($value, array $definition): bool
    {
        // Type validation
        if (isset($definition['type'])) {
            switch ($definition['type']) {
                case 'string':
                    if (! is_string($value)) {
                        return false;
                    }
                    break;
                case 'integer':
                    if (! is_int($value)) {
                        return false;
                    }
                    break;
                case 'array':
                    if (! is_array($value)) {
                        return false;
                    }
                    break;
                case 'boolean':
                    if (! is_bool($value)) {
                        return false;
                    }
                    break;
            }
        }

        // Enum validation
        if (isset($definition['enum']) && ! in_array($value, $definition['enum'])) {
            return false;
        }

        // Range validation for integers
        if (isset($definition['minimum']) && is_numeric($value) && $value < $definition['minimum']) {
            return false;
        }

        if (isset($definition['maximum']) && is_numeric($value) && $value > $definition['maximum']) {
            return false;
        }

        return true;
    }

    /**
     * Execute the actual component logic.
     * Must be implemented by concrete components.
     */
    abstract protected function executeComponent(array $params): array;
}
