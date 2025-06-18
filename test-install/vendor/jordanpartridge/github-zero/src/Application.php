<?php

namespace JordanPartridge\GitHubZero;

use JordanPartridge\GitHubZero\Commands\ReposCommand;
use JordanPartridge\GitHubZero\Commands\CloneCommand;
use JordanPartridge\GithubClient\Github;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('GitHub Zero', '1.0.0');

        $this->setupCommands();
    }

    private function setupCommands(): void
    {
        // Create GitHub client with v2.0 simplified connector API
        $token = $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN') ?? '';
        $connector = new \JordanPartridge\GithubClient\GithubConnector($token);
        $github = new Github($connector);

        // Register commands
        $this->add(new ReposCommand($github));
        $this->add(new CloneCommand($github));
    }
}