<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Token
    |--------------------------------------------------------------------------
    |
    | Your GitHub Personal Access Token. You can also set this via the
    | GITHUB_TOKEN environment variable.
    |
    */
    'token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default Clone Directory
    |--------------------------------------------------------------------------
    |
    | The default directory where repositories will be cloned.
    | Relative to the current working directory.
    |
    */
    'clone_directory' => null, // Uses repository name by default

    /*
    |--------------------------------------------------------------------------
    | Default Repository Limit
    |--------------------------------------------------------------------------
    |
    | The default number of repositories to fetch when listing.
    |
    */
    'default_limit' => 10,

    /*
    |--------------------------------------------------------------------------
    | Auto-open in Editor
    |--------------------------------------------------------------------------
    |
    | Automatically ask to open cloned repositories in your default editor.
    |
    */
    'auto_open_editor' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Editor Command
    |--------------------------------------------------------------------------
    |
    | The command to use when opening repositories in an editor.
    |
    */
    'editor_command' => 'code',
];