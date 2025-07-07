<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero;

use JordanPartridge\GithubClient\Github;
use JordanPartridge\GitHubZero\Traits\InteractsWithGitHub;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    use InteractsWithGitHub;

    private const VERSION = '1.0.0';

    private const NAME = 'GitHub Zero';

    /** @var array<class-string> */
    private array $commandClasses = [
        Commands\Repos::class,
        Commands\CloneRepo::class,
        Commands\Issues::class,
    ];

    public function __construct(?Github $github = null)
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->setupCommands($github);
    }

    private function setupCommands(?Github $github): void
    {
        foreach ($this->commandClasses as $commandClass) {
            // Clone command doesn't need GitHub client in constructor
            if ($commandClass === Commands\CloneRepo::class) {
                $command = new $commandClass;
            } else {
                $command = new $commandClass($github);
            }
            $this->add($command);
        }
    }
}
