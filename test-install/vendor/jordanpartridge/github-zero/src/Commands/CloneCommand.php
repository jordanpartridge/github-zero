<?php

namespace JordanPartridge\GitHubZero\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JordanPartridge\GithubClient\Github;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;

/**
 * GitHub repository cloning command.
 * 
 * Provides functionality to clone GitHub repositories with interactive
 * selection or direct repository specification.
 */
class CloneCommand extends Command
{
    /**
     * Create a new CloneCommand instance.
     * 
     * @param Github $github The GitHub client instance
     */
    public function __construct(
        protected Github $github
    ) {
        parent::__construct();
    }

    /**
     * Configure the command with arguments and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('clone')
            ->setDescription('Clone a GitHub repository with interactive selection')
            ->addArgument('repo', InputArgument::OPTIONAL, 'Repository name (owner/repo) or URL to clone')
            ->addOption('directory', null, InputOption::VALUE_OPTIONAL, 'Directory to clone into')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Use interactive selection');
    }

    /**
     * Execute the clone command.
     * 
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Exit code (0 for success, 1 for error)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->hasGitHubToken()) {
            $output->writeln('<error>🚫 No GitHub token found!</error>');
            $output->writeln('<comment>💡 Set GITHUB_TOKEN environment variable</comment>');
            return 1;
        }

        $this->displayWelcome($output);

        $repo = $input->getArgument('repo');

        if (!$repo || $input->getOption('interactive')) {
            $repo = $this->selectRepository($output);
        }

        if (!$repo) {
            $output->writeln('<comment>👋 No repository selected. See you next time!</comment>');
            return 0;
        }

        return $this->cloneRepository($repo, $input, $output);
    }

    /**
     * Display welcome message and header.
     * 
     * @param OutputInterface $output Command output interface
     */
    private function displayWelcome(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>📥 GitHub Zero - Clone Repository</info>');
        $output->writeln('<comment>═══════════════════════════════════</comment>');
        $output->writeln('');
    }

    /**
     * Interactively select a repository to clone.
     * 
     * @param OutputInterface $output Command output interface
     * @return string|null Selected repository name or null if cancelled
     */
    private function selectRepository(OutputInterface $output): ?string
    {
        try {
            $repos = spin(
                fn () => $this->github->repos()->all(per_page: 20)->json(),
                '🔍 Fetching your repositories...'
            );

            // Check for API errors
            if (is_array($repos) && isset($repos['message'])) {
                $output->writeln('<error>❌ GitHub API Error: ' . $repos['message'] . '</error>');
                return text('📝 Enter repository manually (owner/repo or full URL):');
            }

            if (empty($repos) || !is_array($repos) || !isset($repos[0])) {
                $output->writeln('<comment>📭 No repositories found.</comment>');
                return text('📝 Enter repository manually (owner/repo or full URL):');
            }

            $repoOptions = ['manual' => '⌨️ Enter repository manually'];
            foreach ($repos as $repo) {
                $language = $repo['language'] ? "({$repo['language']})" : '';
                $visibility = $repo['private'] ? '🔒' : '🌍';
                $repoOptions[$repo['full_name']] = "{$visibility} {$repo['full_name']} {$language}";
            }

            $selection = select('📥 Which repository would you like to clone?', $repoOptions);

            if ($selection === 'manual') {
                return text('📝 Enter repository (owner/repo or full URL):');
            }

            return $selection;

        } catch (\Exception $e) {
            $output->writeln('<error>💥 Failed to fetch repositories: ' . $e->getMessage() . '</error>');
            return text('📝 Enter repository manually (owner/repo or full URL):');
        }
    }

    /**
     * Clone the specified repository.
     * 
     * @param string $repo Repository identifier (name or URL)
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Exit code from git clone operation
     */
    private function cloneRepository(string $repo, InputInterface $input, OutputInterface $output): int
    {
        // Parse repository input
        $cloneUrl = $this->parseRepositoryInput($repo);
        $directory = $input->getOption('directory') ?: $this->getDirectoryName($repo);

        $output->writeln("<info>📥 Cloning {$repo}...</info>");
        
        if ($directory && file_exists($directory)) {
            if (!confirm("📁 Directory '{$directory}' exists. Continue anyway?", false)) {
                $output->writeln('<comment>👋 Clone cancelled.</comment>');
                return 0;
            }
        }

        // Build clone command
        $command = "git clone {$cloneUrl}";
        if ($directory) {
            $command .= " \"{$directory}\"";
        }

        $output->writeln("<comment>🚀 Running: {$command}</comment>");
        $output->writeln('');

        // Execute clone with better error handling
        $result = 0;
        $gitOutput = [];
        exec($command . ' 2>&1', $gitOutput, $result);

        if ($result === 0) {
            $output->writeln("<info>✅ Successfully cloned {$repo}!</info>");
            
            if ($directory && is_dir($directory) && confirm("📂 Open {$directory} in your editor?", false)) {
                // Try common editors
                $editors = ['code', 'vim', 'nano'];
                $editorOpened = false;
                
                foreach ($editors as $editor) {
                    if (shell_exec("which {$editor}")) {
                        exec("{$editor} \"{$directory}\" &");
                        $editorOpened = true;
                        break;
                    }
                }
                
                if (!$editorOpened) {
                    $output->writeln("<comment>💡 No supported editor found. Try: cd \"{$directory}\"</comment>");
                }
            }
        } else {
            $output->writeln("<error>💥 Failed to clone {$repo}</error>");
            
            // Provide specific error messages based on common failure scenarios
            $errorOutput = implode("\n", $gitOutput);
            
            if (str_contains($errorOutput, 'Repository not found')) {
                $output->writeln('<error>❌ Repository not found. Check the repository name and your access permissions.</error>');
            } elseif (str_contains($errorOutput, 'Permission denied')) {
                $output->writeln('<error>🔒 Permission denied. Check your GitHub token or SSH key setup.</error>');
            } elseif (str_contains($errorOutput, 'already exists')) {
                $output->writeln('<error>📁 Directory already exists and is not empty.</error>');
            } elseif (str_contains($errorOutput, 'Could not resolve host')) {
                $output->writeln('<error>🌐 Network error. Check your internet connection.</error>');
            } else {
                $output->writeln('<error>Git output:</error>');
                $output->writeln('<comment>' . $errorOutput . '</comment>');
            }
        }

        return $result;
    }

    /**
     * Parse repository input and convert to clone URL.
     * 
     * @param string $repo Repository input (URL or owner/repo format)
     * @return string Git clone URL
     */
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

    /**
     * Extract directory name from repository identifier.
     * 
     * @param string $repo Repository identifier
     * @return string Directory name for cloning
     */
    private function getDirectoryName(string $repo): string
    {
        // Extract directory name from repo and remove .git suffix
        $basename = basename($repo);
        return str_replace('.git', '', $basename);
    }

    /**
     * Check if a GitHub token is available in environment variables.
     * 
     * @return bool True if token exists, false otherwise
     */
    private function hasGitHubToken(): bool
    {
        return !empty($_ENV['GITHUB_TOKEN']) || !empty(getenv('GITHUB_TOKEN'));
    }
}