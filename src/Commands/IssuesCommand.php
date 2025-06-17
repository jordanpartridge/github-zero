<?php

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\spin;

class IssuesCommand extends Command
{
    protected static $defaultName = 'issues';

    protected static $defaultDescription = 'Create, list, and manage GitHub issues';

    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('issues')
            ->setDescription('Create, list, and manage GitHub issues')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action to perform (list, create, show)', 'list')
            ->addArgument('repository', InputArgument::OPTIONAL, 'Repository name (owner/repo or current directory)')
            ->addArgument('number', InputArgument::OPTIONAL, 'Issue number for show action')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Issue title for create action')
            ->addOption('body', null, InputOption::VALUE_OPTIONAL, 'Issue body for create action')
            ->addOption('labels', null, InputOption::VALUE_OPTIONAL, 'Comma-separated labels for create action')
            ->addOption('assignees', null, InputOption::VALUE_OPTIONAL, 'Comma-separated assignees for create action')
            ->addOption('state', null, InputOption::VALUE_OPTIONAL, 'Issue state filter (open, closed, all)', 'open')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (text, json)', 'text')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of issues to display', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');

            return 1;
        }

        $action = $input->getArgument('action');

        return match ($action) {
            'list' => $this->listIssues($input, $output),
            'create' => $this->createIssue($input, $output),
            'show' => $this->showIssue($input, $output),
            default => $this->handleUnknownAction($output, $action)
        };
    }

    private function hasGitHubToken(): bool
    {
        return ! empty($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
    }

    private function handleUnknownAction(OutputInterface $output, string $action): int
    {
        $output->writeln("<error>❌ Unknown action: {$action}. Use: list, create, show</error>");

        return 1;
    }

    private function listIssues(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->getRepository($input, $output);
        if (! $repository) {
            return 1;
        }

        if ($input->getOption('format') === 'text') {
            $output->writeln('<info>🐛 GitHub Issues</info>');
            $output->writeln('<info>═══════════════════</info>');
            $output->writeln('');
        }

        try {
            $issues = spin(
                fn () => $this->github->issues()->all($repository, [
                    'state' => $input->getOption('state'),
                    'per_page' => (int) $input->getOption('limit'),
                ]),
                '🔍 Fetching issues...'
            );

            if ($input->getOption('format') === 'json') {
                $output->writeln(json_encode(array_map(fn ($issue) => $issue->toArray(), $issues), JSON_PRETTY_PRINT));

                return 0;
            }

            $this->displayIssues($output, $issues);

            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>❌ Error fetching issues: {$e->getMessage()}</error>");

            return 1;
        }
    }

    private function createIssue(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->getRepository($input, $output);
        if (! $repository) {
            return 1;
        }

        $title = $input->getOption('title');
        if (! $title) {
            $output->writeln('<error>❌ Title is required for creating issues</error>');
            $output->writeln('<comment>💡 Use --title="Your issue title"</comment>');

            return 1;
        }

        try {
            $data = [
                'title' => $title,
                'body' => $input->getOption('body') ?? '',
            ];

            if ($labels = $input->getOption('labels')) {
                $data['labels'] = explode(',', $labels);
            }

            if ($assignees = $input->getOption('assignees')) {
                $data['assignees'] = explode(',', $assignees);
            }

            $issue = spin(
                fn () => $this->github->issues()->create($repository, $data['title'], $data['body'], $data),
                '🔄 Creating issue...'
            );

            if ($input->getOption('format') === 'json') {
                $output->writeln(json_encode($issue->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln('<info>✅ Issue created successfully!</info>');
                $output->writeln("🔗 {$issue->html_url}");
                $output->writeln("📋 #{$issue->number}: {$issue->title}");
            }

            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>❌ Error creating issue: {$e->getMessage()}</error>");

            return 1;
        }
    }

    private function showIssue(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->getRepository($input, $output);
        if (! $repository) {
            return 1;
        }

        $number = $input->getArgument('number');
        if (! $number) {
            $output->writeln('<error>❌ Issue number is required</error>');
            $output->writeln('<comment>💡 Usage: github issues show owner/repo 123</comment>');

            return 1;
        }

        try {
            $issue = spin(
                fn () => $this->github->issues()->get($repository, (int) $number),
                '🔍 Fetching issue...'
            );

            if ($input->getOption('format') === 'json') {
                $output->writeln(json_encode($issue->toArray(), JSON_PRETTY_PRINT));
            } else {
                $this->displayIssueDetails($output, $issue);
            }

            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>❌ Error fetching issue: {$e->getMessage()}</error>");

            return 1;
        }
    }

    private function getRepository(InputInterface $input, OutputInterface $output): ?string
    {
        $repository = $input->getArgument('repository');

        if ($repository) {
            return $repository;
        }

        // Try to detect from current directory
        if (is_dir('.git')) {
            $remoteUrl = trim(shell_exec('git remote get-url origin 2>/dev/null') ?? '');
            if (preg_match('/github\.com[\/:]([^\/]+\/[^\/]+?)(?:\.git)?$/', $remoteUrl, $matches)) {
                return $matches[1];
            }
        }

        $output->writeln('<error>❌ No repository specified and could not detect from current directory</error>');
        $output->writeln('<comment>💡 Usage: github issues list owner/repo</comment>');
        $output->writeln('<comment>💡 Or run from within a Git repository</comment>');

        return null;
    }

    private function displayIssues(OutputInterface $output, array $issues): void
    {
        if (empty($issues)) {
            $output->writeln('<info>📭 No issues found</info>');

            return;
        }

        foreach ($issues as $index => $issue) {
            $stateEmoji = $issue->state === 'open' ? '🟢' : '🔴';
            $labels = ! empty($issue->labels) ? implode(', ', array_column($issue->labels, 'name')) : '';

            $output->writeln(sprintf(
                '%d. %s #%d: %s',
                $index + 1,
                $stateEmoji,
                $issue->number,
                $issue->title
            ));

            if ($labels) {
                $output->writeln("   🏷️  {$labels}");
            }

            $output->writeln("   👤 {$issue->user->login} • {$issue->created_at}");
            $output->writeln('');
        }
    }

    private function displayIssueDetails(OutputInterface $output, $issue): void
    {
        $stateEmoji = $issue->state === 'open' ? '🟢 Open' : '🔴 Closed';

        $output->writeln("📋 Issue #{$issue->number}");
        $output->writeln('═══════════════════');
        $output->writeln("📝 Title: {$issue->title}");
        $output->writeln("🔗 URL: {$issue->html_url}");
        $output->writeln("📊 State: {$stateEmoji}");
        $output->writeln("👤 Author: {$issue->user->login}");
        $output->writeln("📅 Created: {$issue->created_at}");

        if (! empty($issue->labels)) {
            $labels = implode(', ', array_column($issue->labels, 'name'));
            $output->writeln("🏷️  Labels: {$labels}");
        }

        if (! empty($issue->assignees)) {
            $assignees = implode(', ', array_map(fn ($a) => $a->login, $issue->assignees));
            $output->writeln("👥 Assignees: {$assignees}");
        }

        $output->writeln('');
        $output->writeln('<info>📄 Description:</info>');
        $output->writeln('───────────────');
        $output->writeln($issue->body ?? 'No description provided.');
    }
}
