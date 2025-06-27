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

class ReposCommand extends Command
{
    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('repo:list')
            ->setDescription('List and interact with your GitHub repositories')
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

    private function getFilterOptions(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('interactive')) {
            $options = [
                'type' => select(
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
                'sort' => select(
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
                    default: $input->getOption('limit') ?? '10'
                ),
            ];

            // Advanced filtering options
            if (confirm('🎯 Apply advanced filters?', false)) {
                $language = text('🔤 Filter by language (optional):', default: '');
                if ($language) {
                    $options['language'] = $language;
                }

                $stars = text('⭐ Minimum stars (optional):', default: '');
                if ($stars && is_numeric($stars)) {
                    $options['stars'] = (int) $stars;
                }

                $search = text('🔍 Search term (optional):', default: '');
                if ($search) {
                    $options['search'] = $search;
                }

                $query = text('🤖 Natural language query (optional):',
                    placeholder: 'e.g., "python projects with 10+ stars updated recently"',
                    default: ''
                );
                if ($query) {
                    $options['query'] = $query;
                }
            }

            return $options;
        }

        return [
            'type' => $input->getOption('type') ?? 'all',
            'sort' => $input->getOption('sort') ?? 'updated',
            'limit' => (int) ($input->getOption('limit') ?? 10),
            'language' => $input->getOption('language'),
            'stars' => $input->getOption('stars') ? (int) $input->getOption('stars') : null,
            'active' => $input->getOption('active') ? (int) $input->getOption('active') : null,
            'search' => $input->getOption('search'),
            'query' => $input->getOption('query'),
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
                        sort: $this->mapSortForSearch($options['sort']),
                        order: Direction::DESC,
                        per_page: min(10, (int) $options['limit'])
                    ),
                    '🔍 Searching repositories...'
                );
                
                return $searchResults->items;
            } catch (\Exception $e) {
                $output->writeln("<comment>⚠️  Search unavailable in standalone mode. Falling back to listing...</comment>");
                // Fall through to regular listing
            }
        }
        
        // Fall back to listing user's repositories
        $rawRepos = spin(
            fn () => $this->github->repos()->all(
                type: $this->mapTypeToEnum($options['type']),
                sort: $this->mapSortToEnum($options['sort']),
                per_page: min(10, (int) $options['limit'])
            )->json(),
            '🔍 Fetching your repositories...'
        );
        
        // Check for API errors
        if (is_array($rawRepos) && isset($rawRepos['message'])) {
            throw new \Exception($rawRepos['message']);
        }
        
        return $rawRepos ?? [];
    }
    
    private function buildSearchQuery(array $options): ?string
    {
        
        $queryParts = [];
        
        // Add natural language query or search term
        if ($options['query'] ?? false) {
            return $this->parseNaturalLanguageQuery($options['query']);
        }
        
        if ($options['search'] ?? false) {
            $queryParts[] = $options['search'];
        }
        
        // Add language filter
        if ($options['language'] ?? false) {
            $queryParts[] = "language:{$options['language']}";
        }
        
        // Add stars filter  
        if ($options['stars'] ?? false) {
            $queryParts[] = "stars:>={$options['stars']}";
        }
        
        // Only search if we have specific criteria
        return !empty($queryParts) ? implode(' ', $queryParts) : null;
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
        $cleanQuery = preg_replace('/\b(php|python|javascript|js|typescript|go|rust|java|ruby)\b/', '', $query);
        $cleanQuery = preg_replace('/\d+\+?\s*stars?/', '', $cleanQuery);
        $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));
        
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
            $fullName = is_array($repo) ? $repo['full_name'] : $repo->full_name;
            $isPrivate = is_array($repo) ? $repo['private'] : $repo->private;
            $language = is_array($repo) ? ($repo['language'] ?? null) : $repo->language;
            $description = is_array($repo) ? ($repo['description'] ?? null) : $repo->description;
            
            $visibility = $isPrivate ? '🔒' : '🌍';
            $languageText = $language ? "({$language})" : '';

            $output->writeln(sprintf(
                '<comment>%d.</comment> %s <info>%s</info> %s',
                $index + 1,
                $visibility,
                $fullName,
                $languageText
            ));

            if (!empty($description)) {
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
            $fullName = is_array($repo) ? $repo['full_name'] : $repo->full_name;
            $isPrivate = is_array($repo) ? $repo['private'] : $repo->private;
            $language = is_array($repo) ? ($repo['language'] ?? null) : $repo->language;
            $cloneUrl = is_array($repo) ? $repo['clone_url'] : $repo->clone_url;
            
            $visibility = $isPrivate ? '🔒' : '🌍';
            $languageText = $language ? "({$language})" : '';
            $choices[$cloneUrl] = "{$visibility} {$fullName} {$languageText}";
        }

        $selected = select(
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
