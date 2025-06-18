<?php

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GitHubZero\Components\IssuesComponent;
use JordanPartridge\GitHubZero\Support\ComponentResult;
use JordanPartridge\GitHubZero\Support\ErrorHandler;
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
        protected Github $github,
        protected ?IssuesComponent $component = null
    ) {
        $this->component = $component ?? new IssuesComponent($github);
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
        // Token validation is now handled by the component

        $action = $input->getArgument('action');
        $repository = $this->getRepository($input, $output);

        if (! $repository) {
            return 1;
        }

        try {
            $params = [
                'action' => $action,
                'repository' => $repository,
                'number' => $input->getArgument('number'),
                'title' => $input->getOption('title'),
                'body' => $input->getOption('body'),
                'labels' => $input->getOption('labels') ? explode(',', $input->getOption('labels')) : null,
                'assignees' => $input->getOption('assignees') ? explode(',', $input->getOption('assignees')) : null,
                'state' => $input->getOption('state'),
                'limit' => (int) $input->getOption('limit'),
                'format' => $input->getOption('format'),
            ];

            $result = spin(
                fn () => $this->component->execute($params),
                match ($action) {
                    'list' => '🔍 Fetching issues...',
                    'create' => '🔄 Creating issue...',
                    'show' => '🔍 Fetching issue...',
                    default => '🔄 Processing...',
                }
            );

            if (! ComponentResult::isSuccess($result)) {
                $output->writeln('<error>❌ '.ComponentResult::getError($result).'</error>');

                return ComponentResult::getErrorCode($result);
            }

            $data = ComponentResult::getData($result);

            if ($input->getOption('format') === 'json') {
                $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->displayResults($action, $data, $output);
            }

            return 0;

        } catch (\Exception $e) {
            return ErrorHandler::handle($e, $output);
        }
    }

    private function hasGitHubToken(): bool
    {
        return ! empty($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
    }

    /**
     * Display results based on action type.
     */
    private function displayResults(string $action, array $data, OutputInterface $output): void
    {
        match ($action) {
            'list' => $this->displayIssuesList($data, $output),
            'create' => $this->displayCreatedIssue($data, $output),
            'show' => $this->displayIssueDetails($data, $output),
            default => $output->writeln('<comment>Results processed successfully.</comment>'),
        };
    }

    /**
     * Display list of issues.
     */
    private function displayIssuesList(array $issues, OutputInterface $output): void
    {
        $output->writeln('<info>🐛 GitHub Issues</info>');
        $output->writeln('<info>═══════════════════</info>');
        $output->writeln('');

        if (empty($issues)) {
            $output->writeln('<info>📭 No issues found</info>');

            return;
        }

        foreach ($issues as $index => $issue) {
            $stateEmoji = $issue['state'] === 'open' ? '🟢' : '🔴';
            $labels = ! empty($issue['labels']) ? implode(', ', array_column($issue['labels'], 'name')) : '';

            $output->writeln(sprintf(
                '%d. %s #%d: %s',
                $index + 1,
                $stateEmoji,
                $issue['number'],
                $issue['title']
            ));

            if ($labels) {
                $output->writeln("   🏷️  {$labels}");
            }

            $output->writeln("   👤 {$issue['user']['login']} • {$issue['created_at']}");
            $output->writeln('');
        }
    }

    /**
     * Display created issue.
     */
    private function displayCreatedIssue(array $issue, OutputInterface $output): void
    {
        $output->writeln('<info>✅ Issue created successfully!</info>');
        $output->writeln("🔗 {$issue['html_url']}");
        $output->writeln("📋 #{$issue['number']}: {$issue['title']}");
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

    /**
     * Display detailed issue information.
     */
    private function displayIssueDetails(array $issue, OutputInterface $output): void
    {
        $stateEmoji = $issue['state'] === 'open' ? '🟢 Open' : '🔴 Closed';

        $output->writeln("📋 Issue #{$issue['number']}");
        $output->writeln('═══════════════════');
        $output->writeln("📝 Title: {$issue['title']}");
        $output->writeln("🔗 URL: {$issue['html_url']}");
        $output->writeln("📊 State: {$stateEmoji}");
        $output->writeln("👤 Author: {$issue['user']['login']}");
        $output->writeln("📅 Created: {$issue['created_at']}");

        if (! empty($issue['labels'])) {
            $labels = implode(', ', array_column($issue['labels'], 'name'));
            $output->writeln("🏷️  Labels: {$labels}");
        }

        if (! empty($issue['assignees'])) {
            $assignees = implode(', ', array_column($issue['assignees'], 'login'));
            $output->writeln("👥 Assignees: {$assignees}");
        }

        $output->writeln('');
        $output->writeln('<info>📄 Description:</info>');
        $output->writeln('───────────────');
        $output->writeln($issue['body'] ?? 'No description provided.');
    }
}
