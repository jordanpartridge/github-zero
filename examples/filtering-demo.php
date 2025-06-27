<?php

/**
 * GitHub Zero Filtering Demo
 * Demonstrates the powerful filtering capabilities
 */

require __DIR__.'/../vendor/autoload.php';

use JordanPartridge\GitHubZero\Support\FilterableGitHubData;

// Sample repository data (similar to GitHub API response)
$sampleRepos = [
    [
        'name' => 'laravel-framework',
        'full_name' => 'laravel/framework',
        'description' => 'The Laravel Framework',
        'language' => 'PHP',
        'stargazers_count' => 31000,
        'private' => false,
        'updated_at' => '2024-01-20T10:00:00Z',
    ],
    [
        'name' => 'vue',
        'full_name' => 'vuejs/vue',
        'description' => 'Vue.js - The Progressive JavaScript Framework',
        'language' => 'JavaScript',
        'stargazers_count' => 207000,
        'private' => false,
        'updated_at' => '2024-01-19T15:30:00Z',
    ],
    [
        'name' => 'private-api',
        'full_name' => 'company/private-api',
        'description' => 'Internal API service',
        'language' => 'Python',
        'stargazers_count' => 15,
        'private' => true,
        'updated_at' => '2024-01-18T09:15:00Z',
    ],
    [
        'name' => 'awesome-go',
        'full_name' => 'avelino/awesome-go',
        'description' => 'A curated list of awesome Go frameworks',
        'language' => 'Go',
        'stargazers_count' => 120000,
        'private' => false,
        'updated_at' => '2024-01-17T12:00:00Z',
    ],
    [
        'name' => 'old-project',
        'full_name' => 'user/old-project',
        'description' => 'Legacy PHP project',
        'language' => 'PHP',
        'stargazers_count' => 5,
        'private' => false,
        'updated_at' => '2023-06-01T12:00:00Z',
    ],
];

echo "🐙 GitHub Zero - Filtering Demo\n";
echo "═══════════════════════════════\n\n";

$repos = FilterableGitHubData::repositories($sampleRepos);

echo "📊 All Repositories: {$repos->count()}\n\n";

// Example 1: Filter by language
echo "🔤 PHP Repositories:\n";
$phpRepos = $repos->language('PHP');
foreach ($phpRepos->get() as $repo) {
    echo "  • {$repo['full_name']} ({$repo['stargazers_count']} ⭐)\n";
}
echo "\n";

// Example 2: Filter by minimum stars
echo "⭐ Popular Projects (10,000+ stars):\n";
$popularRepos = $repos->minStars(10000);
foreach ($popularRepos->get() as $repo) {
    echo "  • {$repo['full_name']} - {$repo['language']} ({$repo['stargazers_count']} ⭐)\n";
}
echo "\n";

// Example 3: Natural language queries
echo "🤖 Natural Language Query Examples:\n\n";

$queries = [
    'php projects with 1000+ stars',
    'javascript frameworks',
    'private repositories',
    'projects updated recently',
];

foreach ($queries as $query) {
    echo "Query: \"{$query}\"\n";
    $results = $repos->query($query);
    echo "Results: {$results->count()}\n";
    foreach ($results->get() as $repo) {
        echo "  • {$repo['full_name']}\n";
    }
    echo "\n";
}

// Example 4: Complex chaining
echo "🔗 Complex Filter Chain:\n";
echo "Public repositories with 10,000+ stars, sorted by stars (desc), limit 3:\n";

$complexResults = $repos
    ->visibility('public')
    ->minStars(10000)
    ->sortBy('stargazers_count', 'desc')
    ->limit(3);

foreach ($complexResults->get() as $repo) {
    echo "  • {$repo['full_name']} - {$repo['language']} ({$repo['stargazers_count']} ⭐)\n";
}
echo "\n";

// Example 5: Statistics
echo "📈 Repository Statistics:\n";
$stats = $repos->stats();
print_r($stats);

echo "\n💡 Try these commands:\n";
echo "  ./bin/github repo:list --query=\"php projects with 100+ stars\"\n";
echo "  ./bin/github repo:list --language=javascript --stars=1000 --stats\n";
echo "  ./bin/github repo:list --interactive\n";
echo "\n";
