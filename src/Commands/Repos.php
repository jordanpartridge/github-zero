<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Enums\Direction;
use JordanPartridge\GithubClient\Enums\Repos\Type as RepoType;
use JordanPartridge\GithubClient\Enums\Sort;
use JordanPartridge\GithubClient\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class Repos extends Command
{
    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('repos')
            ->setDescription('List your GitHub repositories')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Repository type (all, owner, public, private, member)')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort repositories by (created, updated, pushed, full_name)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of repositories to display', '10')
            ->addOption('language', null, InputOption::VALUE_OPTIONAL, 'Filter by programming language')
            ->addOption('stars', null, InputOption::VALUE_OPTIONAL, 'Minimum number of stars')
            ->addOption('active', null, InputOption::VALUE_OPTIONAL, 'Show repositories active in last N days', '30')
            ->addOption('search', null, InputOption::VALUE_OPTIONAL, 'Search term for name/description')
            ->addOption('query', null, InputOption::VALUE_OPTIONAL, 'Natural language query (e.g., "python projects with 10+ stars")')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show statistics about filtered results')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive prompts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');

            return 1;
        }

        $this->displayWelcome($output);

        $options = $this->getFilterOptions($input, $output);

        try {
            // Use search or list repositories based on filters
            $repos = $this->fetchRepositories($options, $output);

            if (empty($repos)) {
                $output->writeln('<comment>📭 No repositories found.</comment>');

                return 0;
            }

            $this->displayRepositories($repos, $output);

            if ($input->getOption('interactive')) {
                $this->handleInteractiveMode($repos, $output);
            }

        } catch (\Exception $e) {
            $output->writeln('<error>❌ Error fetching repositories: '.$e->getMessage().'</error>');

            return 1;
        }

        return 0;
    }

    private function hasGitHubToken(): bool
    {
        return ! empty($_ENV['GITHUB_TOKEN']) || ! empty(getenv('GITHUB_TOKEN'));
    }

    private function displayWelcome(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>🐙 GitHub Zero - Repository Manager</info>');
        $output->writeln('<comment>═══════════════════════════════════</comment>');
        $output->writeln('');
    }

    /**
     * @return array{
     *     type: string,
     *     sort: string,
     *     limit: int,
     *     language: string|null,
     *     stars: int|null,
     *     active: int|null,
     *     search: string|null,
     *     query: string|null,
     * }
     */
    private function getFilterOptions(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('interactive')) {
            $options = [
                'type' => (string) select(
                    label: '📋 What type of repositories?',
                    options: [
                        'all' => 'All repositories',
                        'owner' => 'Owned by me',
                        'public' => 'Public repositories',
                        'private' => 'Private repositories',
                        'member' => 'Member repositories',
                    ],
                    default: 'all'
                ),
                'sort' => (string) select(
                    label: '🔄 How should we sort them?',
                    options: [
                        'updated' => 'Recently updated',
                        'created' => 'Recently created',
                        'pushed' => 'Recently pushed',
                        'full_name' => 'Alphabetical',
                    ],
                    default: 'updated'
                ),
                'limit' => (int) select(
                    label: '🔢 How many repositories?',
                    options: [
                        '5' => '5 repositories',
                        '10' => '10 repositories',
                        '20' => '20 repositories',
                        '50' => '50 repositories',
                    ],
                    default: (string) ($input->getOption('limit') ?? '10')
                ),
            ];

            // Advanced filtering options
            if (confirm('🎯 Apply advanced filters?', false)) {
                $language = (string) text('🔤 Filter by language (optional):', default: '');
                if ($language) {
                    $options['language'] = $language;
                }

                $stars = (string) text('⭐ Minimum stars (optional):', default: '');
                if ($stars && is_numeric($stars)) {
                    $options['stars'] = (int) $stars;
                }

                $search = (string) text('🔍 Search term (optional):', default: '');
                if ($search) {
                    $options['search'] = $search;
                }

                $query = (string) text('🤖 Natural language query (optional):',
                    placeholder: 'e.g., "python projects with 10+ stars updated recently"',
                    default: ''
                );
                if ($query) {
                    $options['query'] = $query;
                }
            }

            return $options;
        }

        $limit = $input->getOption('limit');
        $stars = $input->getOption('stars');
        $active = $input->getOption('active');

        return [
            'type' => (string) ($input->getOption('type') ?? 'all'),
            'sort' => (string) ($input->getOption('sort') ?? 'updated'),
            'limit' => (int) (is_string($limit) ? (int) $limit : ($limit ?? 10)),
            'language' => (string) ($input->getOption('language') ?? ''),
            'stars' => (int) (is_string($stars) ? (int) $stars : ($stars ?? 0)),
            'active' => (int) (is_string($active) ? (int) $active : ($active ?? 30)),
            'search' => (string) ($input->getOption('search') ?? ''),
            'query' => (string) ($input->getOption('query') ?? ''),
        ];
    }

    private function fetchRepositories(array $options, OutputInterface $output): array
    {
        // Build GitHub search query from options
        $searchQuery = $this->buildSearchQuery($options);

        if ($searchQuery) {
            $output->writeln("<comment>🔍 Searching: \"{$searchQuery}\"</comment>");

            try {
                $searchResults = spin(
                    fn () => $this->github->repos()->search(
                        query: $searchQuery,
                        sort: $this->mapSortForSearch((string) ($options['sort'] ?? 'updated')),
                        order: Direction::DESC,
                        per_page: min(10, (int) ($options['limit'] ?? 10))
                    ),
                    '🔍 Searching repositories...'
                );

                return $searchResults->items;
            } catch (\Exception $e) {
                $output->writeln('<comment>⚠️  Search unavailable in standalone mode. Falling back to listing...</comment>');
                // Fall through to regular listing
            }
        }

        // Fall back to listing user's repositories
        $rawRepos = spin(
            fn () => $this->github->repos()->all(
                type: $this->mapTypeToEnum((string) ($options['type'] ?? 'all')),
                sort: $this->mapSortToEnum((string) ($options['sort'] ?? 'updated')),
                per_page: min(10, (int) ($options['limit'] ?? 10))
            )->json(),
            '🔍 Fetching your repositories...'
        );

        // Check for API errors
        if (is_array($rawRepos) && isset($rawRepos['message'])) {
            throw new \Exception($rawRepos['message']);
        }

        return $rawRepos;
    }

    private function buildSearchQuery(array $options): ?string
    {

        $queryParts = [];

        // Add natural language query or search term
        if (! empty($options['query'])) {
            return $this->parseNaturalLanguageQuery((string) $options['query']);
        }

        if (! empty($options['search'])) {
            $queryParts[] = (string) $options['search'];
        }

        // Add language filter
        if (! empty($options['language'])) {
            $queryParts[] = 'language:'.((string) $options['language']);
        }

        // Add stars filter
        if (! empty($options['stars'])) {
            $queryParts[] = 'stars:>='.((int) $options['stars']);
        }

        // Only search if we have specific criteria
        return ! empty($queryParts) ? implode(' ', $queryParts) : null;
    }

    private function parseNaturalLanguageQuery(string $query): string
    {
        $searchParts = [];
        $query = strtolower(trim($query));

        // Extract language
        if (preg_match('/\b(php|python|javascript|js|typescript|go|rust|java|ruby)\b/', $query, $matches)) {
            $lang = $matches[1];
            $searchParts[] = "language:{$lang}";
        }

        // Extract stars
        if (preg_match('/(\d+)\+?\s*stars?/', $query, $matches)) {
            $searchParts[] = "stars:>={$matches[1]}";
        }

        // Extract search terms (remove processed parts)
        $cleanQuery = (string) preg_replace('/\b(php|python|javascript|js|typescript|go|rust|java|ruby)\b/', '', $query);
        $cleanQuery = (string) preg_replace('/\d+\+?\s*stars?/', '', $cleanQuery);
        $cleanQuery = trim((string) preg_replace('/\s+/', ' ', $cleanQuery));

        if ($cleanQuery) {
            $searchParts[] = $cleanQuery;
        }

        return implode(' ', $searchParts);
    }

    private function mapSortForSearch(?string $sort): ?string
    {
        return match ($sort) {
            'created' => 'updated', // GitHub search uses 'updated' for recent
            'updated' => 'updated',
            'pushed' => 'updated',
            'full_name' => null, // No equivalent in search
            default => 'stars', // Default to stars for search relevance
        };
    }

    private function displayRepositories(array $repos, OutputInterface $output): void
    {
        $output->writeln('<info>📚 Your Repositories:</info>');
        $output->writeln('');

        foreach ($repos as $index => $repo) {
            // Handle both RepoData objects and array format
            $fullName = is_array($repo) ? (string) $repo['full_name'] : $repo->full_name;
            $isPrivate = is_array($repo) ? (bool) $repo['private'] : $repo->private;
            $language = is_array($repo) ? (string) ($repo['language'] ?? '') : $repo->language;
            $description = is_array($repo) ? (string) ($repo['description'] ?? '') : $repo->description;

            $visibility = $isPrivate ? '🔒' : '🌍';
            $languageText = $language ? "({$language})" : '';

            $output->writeln(sprintf(
                '<comment>%d.</comment> %s <info>%s</info> %s',
                $index + 1,
                $visibility,
                $fullName,
                $languageText
            ));

            if (! empty($description)) {
                $output->writeln('   '.$description);
            }

            $output->writeln('');
        }
    }

    private function handleInteractiveMode(array $repos, OutputInterface $output): void
    {
        $choices = [];
        foreach ($repos as $index => $repo) {
            // Handle both RepoData objects and array format
            $fullName = is_array($repo) ? (string) $repo['full_name'] : $repo->full_name;
            $isPrivate = is_array($repo) ? (bool) $repo['private'] : $repo->private;
            $language = is_array($repo) ? (string) ($repo['language'] ?? '') : $repo->language;
            $cloneUrl = is_array($repo) ? (string) $repo['clone_url'] : $repo->clone_url;

            $visibility = $isPrivate ? '🔒' : '🌍';
            $languageText = $language ? "({$language})" : '';
            $choices[$cloneUrl] = "{$visibility} {$fullName} {$languageText}";
        }

        $selected = (string) select(
            label: '🎯 Select a repository to clone:',
            options: $choices
        );

        if (confirm(
            label: "🚀 Clone {$selected}?",
            default: true
        )) {
            $output->writeln("<info>🔄 Cloning {$selected}...</info>");

            $repoName = basename($selected, '.git');
            exec("git clone {$selected} {$repoName}", $gitOutput, $exitCode);

            if ($exitCode === 0) {
                $output->writeln("<info>✅ Successfully cloned to ./{$repoName}</info>");
            } else {
                $output->writeln('<error>❌ Failed to clone repository</error>');
            }
        }
    }

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
