<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero;

use Illuminate\Support\ServiceProvider;
use JordanPartridge\GithubClient\GithubClientServiceProvider;

class GitHubZeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the GitHub client service provider
        $this->app->register(GithubClientServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\ListCommand::class,
                Commands\RepoCommand::class,
                Commands\IssueCommand::class,
                Commands\ReposCommand::class,
                Commands\CloneCommand::class,
                Commands\IssuesCommand::class,
            ]);
        }
    }
}
