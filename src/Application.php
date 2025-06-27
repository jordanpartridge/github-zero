<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GithubClient\GithubConnector;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    private const VERSION = '1.0.0';

    private const NAME = 'GitHub Zero';

    /** @var array<class-string> */
    private array $commandClasses = [
        // Discovery Commands
        Commands\ListCommand::class,
        Commands\RepoCommand::class,
        Commands\IssueCommand::class,

        // Functional Commands
        Commands\ReposCommand::class,
        Commands\CloneCommand::class,
        Commands\IssuesCommand::class,
    ];

    public function __construct(?Github $github = null)
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->setupCommands($github ?? $this->createGithubClient());
    }

    private function createGithubClient(): Github
    {
        $token = $this->getGithubToken();
        $connector = new GithubConnector($token);

        return new Github($connector);
    }

    private function getGithubToken(): string
    {
        return $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN') ?: '';
    }

    private function setupCommands(Github $github): void
    {
        foreach ($this->commandClasses as $commandClass) {
            // Discovery commands don't need GitHub client
            if (in_array($commandClass, [
                Commands\ListCommand::class,
                Commands\RepoCommand::class,
                Commands\IssueCommand::class,
            ])) {
                $this->add(new $commandClass);
            } else {
                $this->add(new $commandClass($github));
            }
        }
    }
}
