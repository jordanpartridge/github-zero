<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero;

use JordanPartridge\GitHubZero\Commands\CloneRepo;
use JordanPartridge\GitHubZero\Commands\Issues;
use JordanPartridge\GitHubZero\Commands\Repos;

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
            'github:repos' => Repos::class,
            'github:clone' => CloneRepo::class,
            'github:issues' => Issues::class,
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
