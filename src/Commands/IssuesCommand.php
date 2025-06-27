<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

use JordanPartridge\GitHubZero\Support\FilterableGitHubData;

class IssuesCommand extends Command
{
    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('issue:list')
            ->setDescription('List GitHub issues for a repository')
            ->addArgument('repository', InputArgument::REQUIRED, 'Repository name (owner/repo)')
            ->addOption('state', null, InputOption::VALUE_OPTIONAL, 'Issue state filter (open, closed, all)', 'open')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of issues to display', '10')
            ->addOption('assignee', null, InputOption::VALUE_OPTIONAL, 'Filter by assignee username')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'Filter by label')
            ->addOption('query', null, InputOption::VALUE_OPTIONAL, 'Natural language query for filtering')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show statistics about issues')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive prompts')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');

            return 1;
        }

        $this->displayWelcome($output);

        $repository = $input->getArgument('repository');

        if ($input->getOption('interactive')) {
            $repository = $this->selectRepository($repository, $output);
        }

        if (! $repository) {
            $output->writeln('<error>❌ Repository is required. Use format: owner/repo</error>');
            $output->writeln('<comment>💡 Example: ghz issue:list laravel/framework</comment>');

            return 1;
        }

        return $this->listIssues($repository, $input, $output);
    }

    private function hasGitHubToken(): bool
    {
        return ! empty($_ENV['GITHUB_TOKEN']) || ! empty(getenv('GITHUB_TOKEN'));
    }

    private function displayWelcome(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>🐛 GitHub Zero - Issue Manager</info>');
        $output->writeln('<comment>═══════════════════════════════════</comment>');
        $output->writeln('');
    }

    private function selectRepository(?string $default, OutputInterface $output): ?string
    {
        if ($default) {
            return $default;
        }

        try {
            $repos = spin(
                fn () => $this->github->repos()->all(per_page: 20)->json(),
                '🔍 Fetching your repositories...'
            );

            if (is_array($repos) && isset($repos['message'])) {
                $output->writeln('<error>❌ GitHub API Error: '.$repos['message'].'</error>');

                return text('📝 Enter repository manually (owner/repo):');
            }

            if (empty($repos) || ! is_array($repos) || ! isset($repos[0])) {
                return text('📝 Enter repository (owner/repo):');
            }

            $repoOptions = ['manual' => '⌨️ Enter repository manually'];
            foreach ($repos as $repo) {
                $language = $repo['language'] ? "({$repo['language']})" : '';
                $visibility = $repo['private'] ? '🔒' : '🌍';
                $repoOptions[$repo['full_name']] = "{$visibility} {$repo['full_name']} {$language}";
            }

            $selection = select('📦 Select repository:', $repoOptions);

            if ($selection === 'manual') {
                return text('📝 Enter repository (owner/repo):');
            }

            return $selection;

        } catch (\Exception $e) {
            $output->writeln('<error>💥 Failed to fetch repositories: '.$e->getMessage().'</error>');

            return text('📝 Enter repository manually (owner/repo):');
        }
    }

    private function listIssues(string $repository, InputInterface $input, OutputInterface $output): int
    {
        $options = $this->getFilterOptions($input, $output);

        try {
            // Fetch more issues for better filtering
            $fetchLimit = max(100, (int)$options['limit'] * 2);
            
            [$owner, $repo] = explode('/', $repository, 2);
            
            $rawIssues = spin(
                fn () => $this->github->issues()->list($owner, $repo, [
                    'state' => $options['state'],
                    'per_page' => min($fetchLimit, 100),
                ])->json(),
                "🔍 Fetching {$options['state']} issues from {$repository}..."
            );

            if (is_array($rawIssues) && isset($rawIssues['message'])) {
                $output->writeln('<error>❌ GitHub API Error: '.$rawIssues['message'].'</error>');
                return 1;
            }

            if (empty($rawIssues) || !is_array($rawIssues)) {
                $output->writeln("<comment>📭 No {$options['state']} issues found in {$repository}</comment>");
                return 0;
            }

            // Apply advanced filtering
            $filteredIssues = $this->applyFilters($rawIssues, $input, $output);
            
            if ($filteredIssues->isEmpty()) {
                $output->writeln('<comment>📭 No issues match your filters.</comment>');
                return 0;
            }

            // Apply final limit and get results
            $issues = $filteredIssues->limit((int)$options['limit'])->get();

            // Show statistics if requested
            if ($input->getOption('stats')) {
                $this->displayStatistics($filteredIssues, $output);
            }

            if ($input->getOption('json')) {
                $output->writeln(json_encode($issues, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->displayIssues($issues, $output, $repository);

            if ($input->getOption('interactive') && confirm('🔍 View details for an issue?', false)) {
                $this->selectAndShowIssue($issues, $repository, $output);
            }

        } catch (\Exception $e) {
            $output->writeln('<error>❌ Error fetching issues: '.$e->getMessage().'</error>');
            return 1;
        }

        return 0;
    }

    private function getFilterOptions(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('interactive')) {
            $options = [
                'state' => select('🏷️ Which issues?', [
                    'open' => '🟢 Open issues',
                    'closed' => '🔴 Closed issues',
                    'all' => '📋 All issues',
                ], default: $input->getOption('state') ?? 'open'),
                'limit' => (int) select('🔢 How many issues?', [
                    '5' => '5 issues',
                    '10' => '10 issues',
                    '20' => '20 issues',
                    '50' => '50 issues',
                ], default: $input->getOption('limit') ?? '10'),
            ];

            // Advanced filtering options
            if (confirm('🎯 Apply advanced filters?', false)) {
                $assignee = text('👤 Filter by assignee (optional):', default: '');
                if ($assignee) $options['assignee'] = $assignee;

                $label = text('🏷️ Filter by label (optional):', default: '');
                if ($label) $options['label'] = $label;

                $query = text('🤖 Natural language query (optional):', 
                    placeholder: 'e.g., "open bugs assigned to me"',
                    default: ''
                );
                if ($query) $options['query'] = $query;
            }

            return $options;
        }

        return [
            'state' => $input->getOption('state') ?? 'open',
            'limit' => (int) ($input->getOption('limit') ?? 10),
            'assignee' => $input->getOption('assignee'),
            'label' => $input->getOption('label'),
            'query' => $input->getOption('query'),
        ];
    }

    private function applyFilters(array $issues, InputInterface $input, OutputInterface $output): FilterableGitHubData
    {
        $filterable = FilterableGitHubData::issues($issues);

        // Apply natural language query first
        if ($query = $input->getOption('query')) {
            $output->writeln("<comment>🤖 Processing query: \"{$query}\"</comment>");
            $filterable = $filterable->query($query);
        }

        // Apply individual filters
        if ($assignee = $input->getOption('assignee')) {
            $filterable = $filterable->assignedTo($assignee);
        }

        if ($label = $input->getOption('label')) {
            $filterable = $filterable->hasLabel($label);
        }

        return $filterable;
    }

    private function displayStatistics(FilterableGitHubData $data, OutputInterface $output): void
    {
        $stats = $data->stats();
        
        $output->writeln('');
        $output->writeln('<info>📊 Issue Statistics</info>');
        $output->writeln('<comment>══════════════════</comment>');
        
        $output->writeln("🐛 Total: {$stats['total']} issues");
        $output->writeln("🟢 Open: {$stats['open']} | 🔴 Closed: {$stats['closed']}");
        $output->writeln("👤 Assigned: {$stats['assigned']} | ⚪ Unassigned: {$stats['unassigned']}");
        
        $output->writeln('');
    }

    private function displayIssues(array $issues, OutputInterface $output, string $repository): void
    {
        $output->writeln("<info>🐛 Issues in {$repository}:</info>");
        $output->writeln('');

        foreach ($issues as $index => $issue) {
            $state = $issue['state'] === 'open' ? '🟢' : '🔴';
            $assignee = isset($issue['assignee']['login']) ? "👤 {$issue['assignee']['login']}" : '⚪ Unassigned';
            
            $labels = '';
            if (!empty($issue['labels'])) {
                $labelNames = array_slice(array_column($issue['labels'], 'name'), 0, 3);
                $labels = '🏷️ ' . implode(', ', $labelNames);
                if (count($issue['labels']) > 3) {
                    $labels .= '...';
                }
            }

            $output->writeln(sprintf(
                '<comment>%d.</comment> %s <info>#%d %s</info>',
                $index + 1,
                $state,
                $issue['number'],
                $issue['title']
            ));

            $output->writeln("   {$assignee} {$labels}");

            if (!empty($issue['body'])) {
                $body = strlen($issue['body']) > 100 
                    ? substr($issue['body'], 0, 100) . '...' 
                    : $issue['body'];
                $output->writeln("   " . trim($body));
            }

            $output->writeln('');
        }
    }

    private function selectAndShowIssue(array $issues, string $repository, OutputInterface $output): void
    {
        $choices = [];
        foreach ($issues as $issue) {
            $state = $issue['state'] === 'open' ? '🟢' : '🔴';
            $choices[$issue['number']] = "{$state} #{$issue['number']} {$issue['title']}";
        }

        $selectedNumber = select('🔍 Select issue to view:', $choices);
        
        $selectedIssue = collect($issues)->firstWhere('number', $selectedNumber);
        
        if ($selectedIssue) {
            $this->showIssueDetails($selectedIssue, $repository, $output);
        }
    }

    private function showIssueDetails(array $issue, string $repository, OutputInterface $output): void
    {
        $state = $issue['state'] === 'open' ? '🟢 Open' : '🔴 Closed';
        $assignee = isset($issue['assignee']['login']) ? $issue['assignee']['login'] : 'Unassigned';
        
        $output->writeln('');
        $output->writeln('<info>📄 Issue Details</info>');
        $output->writeln('<comment>═══════════════</comment>');
        $output->writeln("Repository: {$repository}");
        $output->writeln("Number: #{$issue['number']}");
        $output->writeln("Title: {$issue['title']}");
        $output->writeln("State: {$state}");
        $output->writeln("Assignee: {$assignee}");
        $output->writeln("Created: " . date('Y-m-d H:i', strtotime($issue['created_at'])));
        
        if (!empty($issue['labels'])) {
            $labels = implode(', ', array_column($issue['labels'], 'name'));
            $output->writeln("Labels: {$labels}");
        }
        
        if (!empty($issue['body'])) {
            $output->writeln('');
            $output->writeln('<comment>Description:</comment>');
            $output->writeln($issue['body']);
        }
        
        $output->writeln('');
        $output->writeln("🔗 URL: {$issue['html_url']}");
        $output->writeln('');
    }
}