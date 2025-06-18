<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Contracts;

interface GitHubComponent
{
    /**
     * Execute the component with given parameters.
     *
     * @param  array  $params  Component parameters
     * @return array Component execution result
     */
    public function execute(array $params = []): array;

    /**
     * Get the component's parameter schema.
     *
     * @return array Schema definition for component parameters
     */
    public function getSchema(): array;

    /**
     * Validate component parameters against schema.
     *
     * @param  array  $params  Parameters to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $params): bool;

    /**
     * Get component metadata.
     *
     * @return array Component name, description, version, etc.
     */
    public function getMetadata(): array;

    /**
     * Get supported output formats.
     *
     * @return array List of supported output formats
     */
    public function getSupportedFormats(): array;
}
