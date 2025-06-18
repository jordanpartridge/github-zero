<?php

namespace JordanPartridge\GitHubZero;

use Illuminate\Support\ServiceProvider;
use JordanPartridge\GitHubZero\Commands\ReposCommand;
use JordanPartridge\GitHubZero\Commands\CloneCommand;
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
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/github-zero.php' => config_path('github-zero.php'),
        ], 'github-zero-config');

        // Merge default configuration
        $this->mergeConfigFrom(__DIR__.'/../config/github-zero.php', 'github-zero');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReposCommand::class,
                CloneCommand::class,
            ]);
        }
    }
}