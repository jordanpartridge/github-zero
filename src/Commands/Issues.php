<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GitHubZero\Support\FilterableGitHubData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class Issues extends Command
{
    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('issues')
            ->setDescription('List GitHub issues')
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
            $repository = $this->selectRepository(is_string($repository) ? $repository : null, $output);
        }

        if (! is_string($repository) || empty($repository)) {
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

    private function selectRepository(?string $default, OutputInterface $output): string
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

            $selection = (string) select('📦 Select repository:', $repoOptions);

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
            $limit = (int) $options['limit'];
            $state = (string) $options['state'];
            $fetchLimit = max(100, $limit * 2);

            $rawIssues = spin(
                fn () => $this->github->issues()->all($repository, [
                    'state' => $state,
                    'per_page' => min($fetchLimit, 100),
                ]),
                "🔍 Fetching {$state} issues from {$repository}..."
            );

            if (empty($rawIssues)) {
                $output->writeln("<comment>📭 No {$state} issues found in {$repository}</comment>");

                return 0;
            }

            $filteredIssues = $this->applyFilters($rawIssues, $input, $output);

            if ($filteredIssues->isEmpty()) {
                $output->writeln('<comment>📭 No issues match your filters.</comment>');

                return 0;
            }

            $issues = $filteredIssues->limit($limit)->get();

            if ($input->getOption('stats')) {
                $this->displayStatistics($filteredIssues, $output);
            }

            if ($input->getOption('json')) {
                $json = json_encode($issues, JSON_PRETTY_PRINT);
                $output->writeln($json ?: '');

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

    /**
     * @return array<string, string|int|null>
     */
    private function getFilterOptions(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('interactive')) {
            $stateDefault = $input->getOption('state');
            $limitDefault = $input->getOption('limit');

            $options = [
                'state' => (string) select(
                    label: '🏷️ Which issues?',
                    options: [
                        'open' => '🟢 Open issues',
                        'closed' => '🔴 Closed issues',
                        'all' => '📋 All issues',
                    ],
                    default: (string) (is_string($stateDefault) ? $stateDefault : 'open')
                ),
                'limit' => (int) select(
                    label: '🔢 How many issues?',
                    options: [
                        '5' => '5 issues',
                        '10' => '10 issues',
                        '20' => '20 issues',
                        '50' => '50 issues',
                    ],
                    default: (int) (is_string($limitDefault) || is_int($limitDefault) ? $limitDefault : '10')
                ),
            ];

            if (confirm('🎯 Apply advanced filters?', false)) {
                $assignee = (string) text('👤 Filter by assignee (optional):', default: '');
                if ($assignee) {
                    $options['assignee'] = $assignee;
                }

                $label = (string) text('🏷️ Filter by label (optional):', default: '');
                if ($label) {
                    $options['label'] = $label;
                }

                $query = (string) text(
                    label: '🤖 Natural language query (optional):',
                    placeholder: 'e.g., "open bugs assigned to me"',
                    default: ''
                );
                if ($query) {
                    $options['query'] = $query;
                }
            }

            return $options;
        }

        $state = $input->getOption('state');
        $limit = $input->getOption('limit');
        $assignee = $input->getOption('assignee');
        $label = $input->getOption('label');
        $query = $input->getOption('query');

        return [
            'state' => (string) (is_string($state) ? $state : 'open'),
            'limit' => (int) (is_int($limit) ? $limit : (is_string($limit) ? (int) $limit : 10)),
            'assignee' => (string) (is_string($assignee) ? $assignee : ''),
            'label' => (string) (is_string($label) ? $label : ''),
            'query' => (string) (is_string($query) ? $query : ''),
        ];
    }

    /**
     * @param  array<int, \JordanPartridge\GithubClient\Data\Issue>  $issues
     */
    private function applyFilters(array $issues, InputInterface $input, OutputInterface $output): FilterableGitHubData
    {
        $filterable = FilterableGitHubData::issues($issues);

        $query = $input->getOption('query');
        if (is_string($query) && ! empty($query)) {
            $output->writeln("<comment>🤖 Processing query: \"{$query}\"</comment>");
            $filterable = $filterable->query($query);
        }

        $assignee = $input->getOption('assignee');
        if (is_string($assignee) && ! empty($assignee)) {
            $filterable = $filterable->assignedTo($assignee);
        }

        $label = $input->getOption('label');
        if (is_string($label) && ! empty($label)) {
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

    /**
     * @param  array<int, \JordanPartridge\GithubClient\Data\Issue>  $issues
     */
    private function displayIssues(array $issues, OutputInterface $output, string $repository): void
    {
        $output->writeln("<info>🐛 Issues in {$repository}:</info>");
        $output->writeln('');

        foreach ($issues as $index => $issue) {
            $state = $issue->state === 'open' ? '🟢' : '🔴';
            $assignee = $issue->assignee ? "👤 {$issue->assignee->login}" : '⚪ Unassigned';

            $labels = '';
            if (! empty($issue->labels)) {
                $labelNames = array_slice(array_column($issue->labels, 'name'), 0, 3);
                $labels = '🏷️ '.implode(', ', $labelNames);
                if (count($issue->labels) > 3) {
                    $labels .= '...';
                }
            }

            $output->writeln(sprintf(
                '<comment>%d.</comment> %s <info>#%d %s</info>',
                $index + 1,
                $state,
                $issue->number,
                $issue->title
            ));

            $output->writeln("   {$assignee} {$labels}");

            if (! empty($issue->body)) {
                $body = strlen($issue->body) > 100
                    ? substr($issue->body, 0, 100).'...'
                    : $issue->body;
                $output->writeln('   '.trim($body));
            }

            $output->writeln('');
        }
    }

    /**
     * @param  array<int, \JordanPartridge\GithubClient\Data\Issue>  $issues
     */
    private function selectAndShowIssue(array $issues, string $repository, OutputInterface $output): void
    {
        $choices = [];
        foreach ($issues as $issue) {
            $state = $issue->state === 'open' ? '🟢' : '🔴';
            $choices[$issue->number] = "{$state} #{$issue->number} {$issue->title}";
        }

        $selectedNumber = select('🔍 Select issue to view:', $choices);

        $selectedIssue = collect($issues)->firstWhere('number', $selectedNumber);

        if ($selectedIssue) {
            $this->showIssueDetails($selectedIssue, $repository, $output);
        }
    }

    private function showIssueDetails(\JordanPartridge\GithubClient\Data\Issue $issue, string $repository, OutputInterface $output): void
    {
        $state = $issue->state === 'open' ? '🟢 Open' : '🔴 Closed';
        $assignee = $issue->assignee->login ?? 'Unassigned';

        $output->writeln('');
        $output->writeln('<info>📄 Issue Details</info>');
        $output->writeln('<comment>═══════════════</comment>');
        $output->writeln("Repository: {$repository}");
        $output->writeln("Number: #{$issue->number}");
        $output->writeln("Title: {$issue->title}");
        $output->writeln("State: {$state}");
        $output->writeln("Assignee: {$assignee}");

        $createdAt = strtotime($issue->created_at);
        if ($createdAt !== false) {
            $output->writeln('Created: '.date('Y-m-d H:i', $createdAt));
        }

        if (! empty($issue->labels)) {
            $labels = implode(', ', array_column($issue->labels, 'name'));
            $output->writeln("Labels: {$labels}");
        }

        if (! empty($issue->body)) {
            $output->writeln('');
            $output->writeln('<comment>Description:</comment>');
            $output->writeln($issue->body);
        }

        $output->writeln('');
        $output->writeln("🔗 URL: {$issue->html_url}");
        $output->writeln('');
    }
}
