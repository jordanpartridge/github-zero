<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JordanPartridge\GithubClient\Github;
use JordanPartridge\GithubClient\Enums\Sort;
use JordanPartridge\GithubClient\Enums\Repos\Type as RepoType;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

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
            ->setName('ghz:repos')
            ->setDescription('List and interact with your GitHub repositories')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Repository type (all, owner, public, private, member)')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort repositories by (created, updated, pushed, full_name)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of repositories to display', '10')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive prompts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');
            return 1;
        }

        $this->displayWelcome($output);

        $options = $this->getFilterOptions($input, $output);

        try {
            $repos = spin(
                fn () => $this->github->repos()->all(
                    type: $this->mapTypeToEnum($options['type']),
                    sort: $this->mapSortToEnum($options['sort']),
                    per_page: $options['limit']
                )->json(),
                '🔍 Fetching your repositories...'
            );

            // Check for API errors
            if (is_array($repos) && isset($repos['message'])) {
                $output->writeln('<error>❌ GitHub API Error: ' . $repos['message'] . '</error>');
                if (isset($repos['documentation_url'])) {
                    $output->writeln('<comment>📖 See: ' . $repos['documentation_url'] . '</comment>');
                }
                return 1;
            }

            if (empty($repos) || !is_array($repos)) {
                $output->writeln('<comment>📭 No repositories found matching your criteria.</comment>');
                return 0;
            }

            // Check if it's a valid repo array (should have numeric indices)
            if (!isset($repos[0])) {
                $output->writeln('<error>❌ Unexpected API response format</error>');
                return 1;
            }

            $this->displayRepositories($repos, $output);

            if ($input->getOption('interactive')) {
                $this->handleInteractiveMode($repos, $output);
            }

        } catch (\Exception $e) {
            $output->writeln('<error>❌ Error fetching repositories: ' . $e->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }

    private function hasGitHubToken(): bool
    {
        return !empty($_ENV['GITHUB_TOKEN']) || !empty(getenv('GITHUB_TOKEN'));
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
            return [
                'type' => select(
                    label: '📋 What type of repositories?',
                    options: [
                        'all' => 'All repositories',
                        'owner' => 'Owned by me',
                        'public' => 'Public repositories',
                        'private' => 'Private repositories',
                        'member' => 'Member repositories'
                    ],
                    default: 'all'
                ),
                'sort' => select(
                    label: '🔄 How should we sort them?',
                    options: [
                        'updated' => 'Recently updated',
                        'created' => 'Recently created',
                        'pushed' => 'Recently pushed',
                        'full_name' => 'Alphabetical'
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
                )
            ];
        }

        return [
            'type' => $input->getOption('type') ?? 'all',
            'sort' => $input->getOption('sort') ?? 'updated',
            'limit' => (int) ($input->getOption('limit') ?? 10)
        ];
    }

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
            
            if (!empty($repo['description'])) {
                $output->writeln('   ' . $repo['description']);
            }
            
            $output->writeln('');
        }
    }

    private function handleInteractiveMode(array $repos, OutputInterface $output): void
    {
        $choices = [];
        foreach ($repos as $index => $repo) {
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