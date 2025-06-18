<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GitHubZero\Components\ReposComponent;
use JordanPartridge\GitHubZero\Support\ComponentResult;
use JordanPartridge\GitHubZero\Support\ErrorHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * GitHub repositories management command.
 *
 * Provides functionality to list, filter, and interact with GitHub repositories
 * using interactive prompts or command-line options.
 */
class ReposCommand extends Command
{
    /** @var string[] Valid repository types */
    private const VALID_TYPES = ['all', 'owner', 'public', 'private', 'member'];

    /** @var string[] Valid sort options */
    private const VALID_SORTS = ['created', 'updated', 'pushed', 'full_name'];

    /** @var int Default repository limit */
    private const DEFAULT_LIMIT = 10;

    /** @var int Maximum repositories per page (GitHub API limit) */
    private const MAX_LIMIT = 100;

    /** @var int Default repositories for interactive selection */
    private const INTERACTIVE_LIMIT = 20;

    /**
     * Create a new ReposCommand instance.
     *
     * @param  Github  $github  The GitHub client instance
     */
    public function __construct(
        protected Github $github,
        protected ?ReposComponent $component = null
    ) {
        $this->component = $component ?? new ReposComponent($github);
        parent::__construct();
    }

    /**
     * Configure the command with options and description.
     */
    protected function configure(): void
    {
        $this
            ->setName('ghz:repos')
            ->setDescription('List and interact with your GitHub repositories')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Repository type (all, owner, public, private, member)')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort repositories by (created, updated, pushed, full_name)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of repositories to display', (string) self::DEFAULT_LIMIT)
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive prompts')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (json, text)', 'text');
    }

    /**
     * Execute the repos command.
     *
     * @param  InputInterface  $input  Command input
     * @param  OutputInterface  $output  Command output
     * @return int Exit code (0 for success, 1 for error)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Token validation is now handled by the component

        $this->displayWelcome($output);

        $options = $this->getFilterOptions($input);

        try {
            // Use component for data fetching
            $result = spin(
                fn () => $this->component->execute($options),
                '🔍 Fetching your repositories...'
            );

            if (! ComponentResult::isSuccess($result)) {
                $output->writeln('<error>❌ '.ComponentResult::getError($result).'</error>');

                return ComponentResult::getErrorCode($result);
            }

            $repos = ComponentResult::getData($result);

            if (empty($repos)) {
                $output->writeln('<comment>📭 No repositories found matching your criteria.</comment>');

                return 0;
            }

            // Handle JSON output format
            if ($input->getOption('format') === 'json') {
                $output->writeln(json_encode($repos, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->displayRepositories($repos, $output);

            if ($input->getOption('interactive')) {
                $this->handleInteractiveMode($repos, $output);
            }

        } catch (\Exception $e) {
            return ErrorHandler::handle($e, $output);
        }

        return 0;
    }

    /**
     * Display welcome message and header.
     *
     * @param  OutputInterface  $output  Command output interface
     */
    private function displayWelcome(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>🐙 GitHub Zero - Repository Manager</info>');
        $output->writeln('<comment>═══════════════════════════════════</comment>');
        $output->writeln('');
    }

    /**
     * Get repository filter options from user input or interactive prompts.
     *
     * @param  InputInterface  $input  Command input
     * @return array{type: string, sort: string, limit: int} Filter options
     */
    private function getFilterOptions(InputInterface $input): array
    {
        if ($input->getOption('interactive')) {
            return [
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
                    default: $input->getOption('limit') ?? (string) self::DEFAULT_LIMIT
                ),
            ];
        }

        // Validate and sanitize inputs
        $type = $input->getOption('type') ?? 'all';
        $sort = $input->getOption('sort') ?? 'updated';
        $limit = (int) ($input->getOption('limit') ?? self::DEFAULT_LIMIT);

        // Validate type option
        if (! in_array($type, self::VALID_TYPES)) {
            $type = 'all';
        }

        // Validate sort option
        if (! in_array($sort, self::VALID_SORTS)) {
            $sort = 'updated';
        }

        // Validate limit (GitHub API allows max 100 per page)
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        return [
            'type' => $type,
            'sort' => $sort,
            'limit' => $limit,
            'format' => $input->getOption('format') ?? 'text',
        ];
    }

    /**
     * Display formatted list of repositories.
     *
     * @param  array  $repos  Array of repository data from GitHub API
     * @param  OutputInterface  $output  Command output interface
     */
    private function displayRepositories(array $repos, OutputInterface $output): void
    {
        $output->writeln('<info>📚 Your Repositories:</info>');
        $output->writeln('');

        foreach ($repos as $index => $repo) {
            $visibility = $repo['private'] ? '🔒' : '🌍';
            $language = $repo['language'] ?? null;
            $language = $language ? "({$language})" : '';

            $output->writeln(sprintf(
                '<comment>%d.</comment> %s <info>%s</info> %s',
                $index + 1,
                $visibility,
                $repo['full_name'],
                $language
            ));

            if (! empty($repo['description'])) {
                $output->writeln('   '.$repo['description']);
            }

            $output->writeln('');
        }
    }

    /**
     * Handle interactive repository selection and cloning.
     *
     * @param  array  $repos  Array of repository data
     * @param  OutputInterface  $output  Command output interface
     */
    private function handleInteractiveMode(array $repos, OutputInterface $output): void
    {
        $choices = [];
        foreach ($repos as $repo) {
            $visibility = $repo['private'] ? '🔒' : '🌍';
            $language = $repo['language'] ?? null;
            $language = $language ? "({$language})" : '';
            $choices[$repo['clone_url']] = "{$visibility} {$repo['full_name']} {$language}";
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
}
