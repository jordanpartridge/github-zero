<?php

declare(strict_types=1);

use JordanPartridge\GitHubZero\Application;

it('creates application with correct name and version', function () {
    $app = new Application;

    expect($app->getName())->toBe('GitHub Zero')
        ->and($app->getVersion())->toBe('1.0.0');
});

it('has required commands registered', function () {
    $_ENV['GITHUB_TOKEN'] = 'fake-token-for-testing';

    $app = new Application;

    expect($app->has('list'))->toBeTrue()
        ->and($app->has('repo'))->toBeTrue()
        ->and($app->has('issue'))->toBeTrue()
        ->and($app->has('repo:list'))->toBeTrue()
        ->and($app->has('repo:clone'))->toBeTrue()
        ->and($app->has('issue:list'))->toBeTrue();

    unset($_ENV['GITHUB_TOKEN']);
});
