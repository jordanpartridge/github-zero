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

class CloneRepo extends Command
{
    use \JordanPartridge\GitHubZero\Traits\InteractsWithGitHub;

    public function __construct()
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->setName('clone')
            ->setDescription('Clone a GitHub repository')
            ->addArgument('repo', InputArgument::OPTIONAL, 'Repository name (owner/repo) or URL to clone')
            ->addOption('directory', null, InputOption::VALUE_OPTIONAL, 'Directory to clone into')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive selection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');

            return 1;
        }

        $this->displayWelcome($output);

        $repoArgument = $input->getArgument('repo');
        $repo = (string) ($input->getArgument('repo') ?? '');
        $interactiveOption = (bool) $input->getOption('interactive');

        if (empty($repo) || (bool) $interactiveOption) {
            $repo = $this->selectRepository($output);
        }

        if (empty($repo)) {
            $output->writeln('<comment>👋 No repository selected. See you next time!</comment>');

            return 0;
        }

        return $this->cloneRepository($repo, $input, $output);
    }

    private function displayWelcome(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>📥 GitHub Zero - Clone Repository</info>');
        $output->writeln('<comment>═══════════════════════════════════</comment>');
        $output->writeln('');
    }

    private function selectRepository(OutputInterface $output): string
    {
        try {
            $github = $this->createGithubClient();
            $repos = spin(
                fn () => $github->repos()->all(per_page: 20)->json(),
                '🔍 Fetching your repositories...'
            );

            // Check for API errors
            if (is_array($repos) && isset($repos['message'])) {
                $output->writeln('<error>❌ GitHub API Error: '.$repos['message'].'</error>');

                return (string) text('📝 Enter repository manually (owner/repo or full URL):');
            }

            if (empty($repos) || ! is_array($repos) || ! isset($repos[0])) {
                $output->writeln('<comment>📭 No repositories found.</comment>');

                return (string) text('📝 Enter repository manually (owner/repo or full URL):');
            }

            $repoOptions = ['manual' => '⌨️ Enter repository manually'];
            foreach ($repos as $repo) {
                $language = $repo['language'] ? "({$repo['language']})" : '';
                $visibility = $repo['private'] ? '🔒' : '🌍';
                $repoOptions[$repo['full_name']] = "{$visibility} {$repo['full_name']} {$language}";
            }

            $selection = (string) select('📥 Which repository would you like to clone?', $repoOptions);

            if ($selection === 'manual') {
                return (string) text('📝 Enter repository (owner/repo or full URL):');
            }

            return $selection;

        } catch (\Exception $e) {
            $output->writeln('<error>💥 Failed to fetch repositories: '.$e->getMessage().'</error>');

            return (string) text('📝 Enter repository manually (owner/repo or full URL):');
        }
    }

    private function cloneRepository(string $repo, InputInterface $input, OutputInterface $output): int
    {
        // Parse repository input
        $cloneUrl = $this->parseRepositoryInput($repo);
        $directory = (string) ($input->getOption('directory') ?: $this->getDirectoryName($repo));

        $output->writeln("<info>📥 Cloning {$repo}...</info>");

        if ($directory && file_exists($directory)) {
            if (! confirm("📁 Directory '{$directory}' exists. Continue anyway?", false)) {
                $output->writeln('<comment>👋 Clone cancelled.</comment>');

                return 0;
            }
        }

        // Execute clone using Process to prevent shell injection
        $args = ['git', 'clone', $cloneUrl];
        if (! empty($directory)) {
            $args[] = $directory;
        }
        $process = new \Symfony\Component\Process\Process($args);

        $output->writeln('<comment>🚀 Running: '.implode(' ', $args).'</comment>');
        $output->writeln('');

        $process->run();
        $result = $process->getExitCode();

        if ($result === 0) {
            $output->writeln("<info>✅ Successfully cloned {$repo}!</info>");

            if ($directory && confirm("📂 Open {$directory} in your editor?", false)) {
                exec("code \"{$directory}\"");
            }
        } else {
            $output->writeln("<error>💥 Failed to clone {$repo}</error>");
        }

        return $result ?? 1;
    }

    private function parseRepositoryInput(string $repo): string
    {
        // If it's already a full URL, return as-is
        if (str_starts_with($repo, 'https://') || str_starts_with($repo, 'git@')) {
            return $repo;
        }

        // If it's in owner/repo format, convert to HTTPS URL
        if (str_contains($repo, '/')) {
            return "https://github.com/{$repo}.git";
        }

        // Assume it's just a repo name, try to find the owner
        return "https://github.com/{$repo}.git";
    }

    private function getDirectoryName(string $repo): string
    {
        // Extract directory name from repo and remove .git suffix
        $basename = basename($repo);

        return str_replace('.git', '', $basename);
    }

    
}
