<?php

declare(strict_types=1);

use JordanPartridge\GitHubZero\Support\ComponentResult;

it('builds a successful result', function () {
    $result = ComponentResult::success(['repo' => 'github-zero'], ['page' => 1]);

    expect(ComponentResult::isSuccess($result))->toBeTrue()
        ->and(ComponentResult::getData($result))->toBe(['repo' => 'github-zero'])
        ->and($result['metadata'])->toBe(['page' => 1])
        ->and($result['timestamp'])->toBeInt();
});

it('builds an error result', function () {
    $result = ComponentResult::error('Not found', 404);

    expect(ComponentResult::isSuccess($result))->toBeFalse()
        ->and(ComponentResult::getError($result))->toBe('Not found')
        ->and(ComponentResult::getErrorCode($result))->toBe(404)
        ->and(ComponentResult::getData($result))->toBe([]);
});

it('falls back to defaults for an empty result', function () {
    expect(ComponentResult::isSuccess([]))->toBeFalse()
        ->and(ComponentResult::getError([]))->toBe('Unknown error')
        ->and(ComponentResult::getErrorCode([]))->toBe(1);
});
