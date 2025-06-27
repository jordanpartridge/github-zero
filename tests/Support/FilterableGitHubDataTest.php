<?php

declare(strict_types=1);

use JordanPartridge\GitHubZero\Support\FilterableGitHubData;

beforeEach(function () {
    $this->sampleRepos = [
        [
            'name' => 'awesome-php',
            'full_name' => 'ziadoz/awesome-php',
            'description' => 'A curated list of awesome PHP frameworks',
            'language' => 'PHP',
            'stargazers_count' => 2500,
            'private' => false,
            'updated_at' => '2024-01-15T10:00:00Z',
        ],
        [
            'name' => 'flask-app',
            'full_name' => 'user/flask-app',
            'description' => 'Simple Python web application',
            'language' => 'Python',
            'stargazers_count' => 45,
            'private' => true,
            'updated_at' => '2024-01-10T15:30:00Z',
        ],
        [
            'name' => 'react-components',
            'full_name' => 'user/react-components',
            'description' => 'Reusable React components library',
            'language' => 'JavaScript',
            'stargazers_count' => 150,
            'private' => false,
            'updated_at' => '2024-01-20T09:15:00Z',
        ],
        [
            'name' => 'old-project',
            'full_name' => 'user/old-project',
            'description' => 'Legacy project',
            'language' => 'Java',
            'stargazers_count' => 5,
            'private' => false,
            'updated_at' => '2023-06-01T12:00:00Z',
        ],
    ];

    $this->sampleIssues = [
        [
            'title' => 'Fix authentication bug',
            'state' => 'open',
            'assignee' => ['login' => 'johndoe'],
            'labels' => [
                ['name' => 'bug'],
                ['name' => 'critical'],
            ],
        ],
        [
            'title' => 'Add new feature',
            'state' => 'closed',
            'assignee' => null,
            'labels' => [
                ['name' => 'feature'],
                ['name' => 'enhancement'],
            ],
        ],
    ];
});

it('creates filterable data from repositories', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);

    expect($filterable->count())->toBe(4)
        ->and($filterable->get())->toHaveCount(4);
});

it('creates filterable data from issues', function () {
    $filterable = FilterableGitHubData::issues($this->sampleIssues);

    expect($filterable->count())->toBe(2)
        ->and($filterable->get())->toHaveCount(2);
});

it('filters by language', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $phpRepos = $filterable->language('PHP');

    expect($phpRepos->count())->toBe(1)
        ->and($phpRepos->first()['name'])->toBe('awesome-php');
});

it('filters by minimum stars', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $popularRepos = $filterable->minStars(100);

    expect($popularRepos->count())->toBe(2);
});

it('filters by visibility', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);

    $privateRepos = $filterable->visibility('private');
    expect($privateRepos->count())->toBe(1);

    $publicRepos = $filterable->visibility('public');
    expect($publicRepos->count())->toBe(3);
});

it('searches by term', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $reactRepos = $filterable->search('react');

    expect($reactRepos->count())->toBe(1)
        ->and($reactRepos->first()['name'])->toBe('react-components');
});

it('filters by issue state', function () {
    $filterable = FilterableGitHubData::issues($this->sampleIssues);

    $openIssues = $filterable->state('open');
    expect($openIssues->count())->toBe(1);

    $closedIssues = $filterable->state('closed');
    expect($closedIssues->count())->toBe(1);
});

it('filters by assignee', function () {
    $filterable = FilterableGitHubData::issues($this->sampleIssues);
    $assignedIssues = $filterable->assignedTo('johndoe');

    expect($assignedIssues->count())->toBe(1);
});

it('filters by labels', function () {
    $filterable = FilterableGitHubData::issues($this->sampleIssues);
    $bugIssues = $filterable->hasLabel('bug');

    expect($bugIssues->count())->toBe(1);
});

it('processes natural language queries', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);

    // Test language query
    $phpRepos = $filterable->query('php projects');
    expect($phpRepos->count())->toBe(1);

    // Test stars query
    $popularRepos = $filterable->query('100+ stars');
    expect($popularRepos->count())->toBe(2);

    // Test visibility query
    $privateRepos = $filterable->query('private repositories');
    expect($privateRepos->count())->toBe(1);
});

it('chains multiple filters', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);

    $filtered = $filterable
        ->visibility('public')
        ->minStars(100)
        ->search('awesome');

    expect($filtered->count())->toBe(1)
        ->and($filtered->first()['name'])->toBe('awesome-php');
});

it('sorts data', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $sorted = $filterable->sortBy('stargazers_count', 'desc');

    $results = $sorted->get();
    expect($results[0]['stargazers_count'])->toBe(2500);
});

it('limits results', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $limited = $filterable->limit(2);

    expect($limited->count())->toBe(2);
});

it('generates repository statistics', function () {
    $filterable = FilterableGitHubData::repositories($this->sampleRepos);
    $stats = $filterable->stats();

    expect($stats)->toHaveKeys(['total', 'languages', 'private', 'public', 'total_stars', 'average_stars'])
        ->and($stats['total'])->toBe(4)
        ->and($stats['private'])->toBe(1)
        ->and($stats['public'])->toBe(3)
        ->and($stats['total_stars'])->toBe(2700);
});

it('generates issue statistics', function () {
    $filterable = FilterableGitHubData::issues($this->sampleIssues);
    $stats = $filterable->stats();

    expect($stats)->toHaveKeys(['total', 'open', 'closed', 'assigned', 'unassigned'])
        ->and($stats['total'])->toBe(2)
        ->and($stats['open'])->toBe(1)
        ->and($stats['closed'])->toBe(1)
        ->and($stats['assigned'])->toBe(1)
        ->and($stats['unassigned'])->toBe(1);
});
