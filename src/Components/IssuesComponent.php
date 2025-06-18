<?php

namespace JordanPartridge\GitHubZero\Components;

class IssuesComponent extends AbstractGitHubComponent
{
    /**
     * Get component metadata.
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'issues',
            'description' => 'Create, list, and manage GitHub issues',
            'version' => '1.0.0',
            'category' => 'issues',
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
                'action' => [
                    'type' => 'string',
                    'enum' => ['list', 'create', 'show'],
                    'default' => 'list',
                    'description' => 'Action to perform',
                ],
                'repository' => [
                    'type' => 'string',
                    'description' => 'Repository name (owner/repo)',
                ],
                'number' => [
                    'type' => 'integer',
                    'description' => 'Issue number for show action',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Issue title for create action',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'Issue body for create action',
                ],
                'labels' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Labels for create action',
                ],
                'assignees' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Assignees for create action',
                ],
                'state' => [
                    'type' => 'string',
                    'enum' => ['open', 'closed', 'all'],
                    'default' => 'open',
                    'description' => 'Issue state filter for list action',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10,
                    'description' => 'Number of issues to return for list action',
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
     * Execute the issues component.
     */
    protected function executeComponent(array $params): array
    {
        if (! $this->hasGitHubToken()) {
            throw new \Exception('No GitHub token found. Set GITHUB_TOKEN environment variable.');
        }

        $action = $params['action'] ?? 'list';
        $repository = $params['repository'];

        return match ($action) {
            'list' => $this->listIssues($repository, $params),
            'create' => $this->createIssue($repository, $params),
            'show' => $this->showIssue($repository, $params),
            default => throw new \Exception("Unknown action: {$action}. Use: list, create, show"),
        };
    }

    /**
     * List issues for repository.
     */
    private function listIssues(string $repository, array $params): array
    {
        $state = $params['state'] ?? 'open';
        $limit = $params['limit'] ?? 10;

        $issues = $this->github->issues()->all($repository, [
            'state' => $state,
            'per_page' => $limit,
        ]);

        return array_map(function ($issue) {
            return [
                'id' => $issue->id,
                'number' => $issue->number,
                'title' => $issue->title,
                'body' => $issue->body,
                'state' => $issue->state,
                'user' => [
                    'login' => $issue->user->login,
                    'id' => $issue->user->id,
                    'avatar_url' => $issue->user->avatar_url,
                ],
                'labels' => array_map(fn ($label) => [
                    'name' => $label->name,
                    'color' => $label->color,
                ], $issue->labels ?? []),
                'assignees' => array_map(fn ($assignee) => [
                    'login' => $assignee->login,
                    'id' => $assignee->id,
                ], $issue->assignees ?? []),
                'html_url' => $issue->html_url,
                'created_at' => $issue->created_at,
                'updated_at' => $issue->updated_at,
            ];
        }, $issues);
    }

    /**
     * Create a new issue.
     */
    private function createIssue(string $repository, array $params): array
    {
        $title = $params['title'] ?? null;
        if (! $title) {
            throw new \Exception('Title is required for creating issues');
        }

        $data = [
            'title' => $title,
            'body' => $params['body'] ?? '',
        ];

        if (isset($params['labels']) && is_array($params['labels'])) {
            $data['labels'] = $params['labels'];
        }

        if (isset($params['assignees']) && is_array($params['assignees'])) {
            $data['assignees'] = $params['assignees'];
        }

        $issue = $this->github->issues()->create($repository, $data['title'], $data['body'], $data);

        return [
            'id' => $issue->id,
            'number' => $issue->number,
            'title' => $issue->title,
            'body' => $issue->body,
            'state' => $issue->state,
            'html_url' => $issue->html_url,
            'created_at' => $issue->created_at,
        ];
    }

    /**
     * Show a specific issue.
     */
    private function showIssue(string $repository, array $params): array
    {
        $number = $params['number'] ?? null;
        if (! $number) {
            throw new \Exception('Issue number is required');
        }

        $issue = $this->github->issues()->get($repository, (int) $number);

        return [
            'id' => $issue->id,
            'number' => $issue->number,
            'title' => $issue->title,
            'body' => $issue->body,
            'state' => $issue->state,
            'user' => [
                'login' => $issue->user->login,
                'id' => $issue->user->id,
                'avatar_url' => $issue->user->avatar_url,
            ],
            'labels' => array_map(fn ($label) => [
                'name' => $label->name,
                'color' => $label->color,
            ], $issue->labels ?? []),
            'assignees' => array_map(fn ($assignee) => [
                'login' => $assignee->login,
                'id' => $assignee->id,
            ], $issue->assignees ?? []),
            'html_url' => $issue->html_url,
            'created_at' => $issue->created_at,
            'updated_at' => $issue->updated_at,
        ];
    }
}
