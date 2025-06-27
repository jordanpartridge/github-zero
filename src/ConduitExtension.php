<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero;

use JordanPartridge\GitHubZero\Commands\CloneCommand;
use JordanPartridge\GitHubZero\Commands\IssuesCommand;
use JordanPartridge\GitHubZero\Commands\ReposCommand;

class ConduitExtension
{
    /**
     * Get the extension name.
     */
    public function name(): string
    {
        return 'GitHub Zero';
    }

    /**
     * Get the extension version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get the extension description.
     */
    public function description(): string
    {
        return 'Lightweight GitHub CLI operations';
    }

    /**
     * Get the commands provided by this extension.
     */
    public function commands(): array
    {
        return [
            'github:repos' => ReposCommand::class,
            'github:clone' => CloneCommand::class,
            'github:issues' => IssuesCommand::class,
        ];
    }

    /**
     * Get any configuration this extension needs.
     */
    public function config(): array
    {
        return [
            'github_token' => env('GITHUB_TOKEN'),
        ];
    }
}
