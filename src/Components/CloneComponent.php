<?php

namespace JordanPartridge\GitHubZero\Components;

class CloneComponent extends AbstractGitHubComponent
{
    /**
     * Get component metadata.
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'clone',
            'description' => 'Clone GitHub repositories',
            'version' => '1.0.0',
            'category' => 'repository',
        ];
    }

    /**
     * Get parameter schema.
     */
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repository' => [
                    'type' => 'string',
                    'description' => 'Repository identifier (owner/repo or URL)',
                ],
                'directory' => [
                    'type' => 'string',
                    'description' => 'Target directory for clone',
                ],
                'force' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force clone even if directory exists',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['json', 'array', 'text'],
                    'default' => 'array',
                    'description' => 'Output format',
                ],
            ],
            'required' => ['repository'],
        ];
    }

    /**
     * Get supported output formats.
     */
    public function getSupportedFormats(): array
    {
        return ['json', 'array', 'text'];
    }

    /**
     * Execute the clone component.
     */
    protected function executeComponent(array $params): array
    {
        if (! $this->hasGitHubToken()) {
            throw new \Exception('No GitHub token found. Set GITHUB_TOKEN environment variable.');
        }

        $repository = $params['repository'];
        $directory = $params['directory'] ?? $this->getDirectoryName($repository);
        $force = $params['force'] ?? false;

        // Parse repository input and get clone URL
        $cloneUrl = $this->parseRepositoryInput($repository);

        // Check if directory exists
        if ($directory && file_exists($directory) && ! $force) {
            throw new \Exception("Directory '{$directory}' already exists. Use force parameter to override.");
        }

        // Build clone command
        $command = "git clone {$cloneUrl}";
        if ($directory) {
            $command .= " \"{$directory}\"";
        }

        // Execute clone
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $errorOutput = implode("\n", $output);

            // Provide specific error messages
            if (str_contains($errorOutput, 'Repository not found')) {
                throw new \Exception('Repository not found. Check the repository name and your access permissions.');
            } elseif (str_contains($errorOutput, 'Permission denied')) {
                throw new \Exception('Permission denied. Check your GitHub token or SSH key setup.');
            } elseif (str_contains($errorOutput, 'already exists')) {
                throw new \Exception('Directory already exists and is not empty.');
            } elseif (str_contains($errorOutput, 'Could not resolve host')) {
                throw new \Exception('Network error. Check your internet connection.');
            } else {
                throw new \Exception('Clone failed: '.$errorOutput);
            }
        }

        return [
            'repository' => $repository,
            'clone_url' => $cloneUrl,
            'directory' => $directory,
            'command' => $command,
            'output' => $output,
            'success' => true,
        ];
    }

    /**
     * Parse repository input and convert to clone URL.
     */
    private function parseRepositoryInput(string $repo): string
    {
        // If it's already a full URL, return as-is
        if (str_starts_with($repo, 'https://') || str_starts_with($repo, 'git@')) {
            return $repo;
        }

        // If it's in owner/repo format, convert to HTTPS URL
        if (str_contains($repo, '/')) {
            return "https://github.com/{$repo}.git";
        }

        // Assume it's just a repo name
        return "https://github.com/{$repo}.git";
    }

    /**
     * Extract directory name from repository identifier.
     */
    private function getDirectoryName(string $repo): string
    {
        $basename = basename($repo);

        return str_replace('.git', '', $basename);
    }
}
