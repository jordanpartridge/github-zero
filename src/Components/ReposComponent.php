<?php

namespace JordanPartridge\GitHubZero\Components;

use JordanPartridge\GithubClient\Enums\Repos\Type as RepoType;
use JordanPartridge\GithubClient\Enums\Sort;

class ReposComponent extends AbstractGitHubComponent
{
    /**
     * Get component metadata.
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'repos',
            'description' => 'List and filter GitHub repositories',
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
                'type' => [
                    'type' => 'string',
                    'enum' => ['all', 'owner', 'public', 'private', 'member'],
                    'default' => 'all',
                    'description' => 'Repository type filter',
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['created', 'updated', 'pushed', 'full_name'],
                    'default' => 'updated',
                    'description' => 'Sort repositories by',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10,
                    'description' => 'Number of repositories to return',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['json', 'array', 'text'],
                    'default' => 'array',
                    'description' => 'Output format',
                ],
            ],
            'required' => [],
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
     * Execute the repos component.
     */
    protected function executeComponent(array $params): array
    {
        if (! $this->hasGitHubToken()) {
            throw new \Exception('No GitHub token found. Set GITHUB_TOKEN environment variable.');
        }

        // Apply defaults
        $type = $params['type'] ?? 'all';
        $sort = $params['sort'] ?? 'updated';
        $limit = $params['limit'] ?? 10;

        // Fetch repositories
        $repos = $this->github->repos()->all(
            type: $this->mapTypeToEnum($type),
            sort: $this->mapSortToEnum($sort),
            per_page: $limit
        )->json();

        // Handle API errors
        if (is_array($repos) && isset($repos['message'])) {
            throw new \Exception('GitHub API Error: '.$repos['message']);
        }

        if (empty($repos) || ! is_array($repos)) {
            return [];
        }

        // Validate response format
        if (! isset($repos[0])) {
            throw new \Exception('Unexpected API response format');
        }

        // Transform data for component interface
        return array_map(function ($repo) {
            return [
                'id' => $repo['id'],
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'description' => $repo['description'],
                'language' => $repo['language'],
                'private' => $repo['private'],
                'clone_url' => $repo['clone_url'],
                'html_url' => $repo['html_url'],
                'created_at' => $repo['created_at'],
                'updated_at' => $repo['updated_at'],
                'pushed_at' => $repo['pushed_at'],
                'stargazers_count' => $repo['stargazers_count'],
                'watchers_count' => $repo['watchers_count'],
                'forks_count' => $repo['forks_count'],
            ];
        }, $repos);
    }

    /**
     * Map string repository type to RepoType enum.
     */
    private function mapTypeToEnum(?string $type): RepoType
    {
        return match ($type) {
            'owner' => RepoType::Owner,
            'public' => RepoType::Public,
            'private' => RepoType::Private,
            'member' => RepoType::Member,
            default => RepoType::All,
        };
    }

    /**
     * Map string sort option to Sort enum.
     */
    private function mapSortToEnum(?string $sort): Sort
    {
        return match ($sort) {
            'created' => Sort::CREATED,
            'updated' => Sort::UPDATED,
            'pushed' => Sort::PUSHED,
            'full_name' => Sort::FULL_NAME,
            default => Sort::UPDATED,
        };
    }
}
