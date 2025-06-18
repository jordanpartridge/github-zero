<?php

namespace JordanPartridge\GithubClient\Data\Pulls;

use JordanPartridge\GithubClient\Enums\Direction;
use JordanPartridge\GithubClient\Enums\Pulls\Sort;
use JordanPartridge\GithubClient\Enums\Pulls\State;
use Spatie\LaravelData\Data;

class Params extends Data
{
    public function __construct(
        public readonly ?State $state,
        public readonly ?string $head,
        public readonly ?string $base,
        public readonly ?Sort $sort,
        public readonly ?Direction $direction,
        public readonly ?string $per_page,
        public readonly ?string $page,
    ) {}
}
