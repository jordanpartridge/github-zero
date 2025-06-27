<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class FilterableGitHubData
{
    private Collection $data;

    private string $type;

    public function __construct(array $data, string $type)
    {
        $this->data = collect($data);
        $this->type = $type;
    }

    /**
     * Create from repository data
     */
    public static function repositories(array $repos): self
    {
        return new self($repos, 'repository');
    }

    /**
     * Create from issue data
     */
    public static function issues(array $issues): self
    {
        return new self($issues, 'issue');
    }

    /**
     * Filter by language
     */
    public function language(string $language): self
    {
        return $this->filter(function ($item) use ($language) {
            return isset($item['language']) &&
                   strtolower($item['language']) === strtolower($language);
        });
    }

    /**
     * Filter by multiple languages
     */
    public function languages(array $languages): self
    {
        $languages = array_map('strtolower', $languages);

        return $this->filter(function ($item) use ($languages) {
            return isset($item['language']) &&
                   in_array(strtolower($item['language']), $languages);
        });
    }

    /**
     * Filter by stars (repositories only)
     */
    public function stars(?int $min = null, ?int $max = null): self
    {
        return $this->filter(function ($item) use ($min, $max) {
            $stars = $item['stargazers_count'] ?? 0;

            if ($min !== null && $stars < $min) {
                return false;
            }
            if ($max !== null && $stars > $max) {
                return false;
            }

            return true;
        });
    }

    /**
     * Filter by minimum stars
     */
    public function minStars(int $min): self
    {
        return $this->stars($min);
    }

    /**
     * Filter by visibility (public/private)
     */
    public function visibility(string $visibility): self
    {
        $isPrivate = strtolower($visibility) === 'private';

        return $this->filter(function ($item) use ($isPrivate) {
            return ($item['private'] ?? false) === $isPrivate;
        });
    }

    /**
     * Filter by date range
     */
    public function updatedSince(string $date): self
    {
        $since = Carbon::parse($date);

        return $this->filter(function ($item) use ($since) {
            $updated = Carbon::parse($item['updated_at'] ?? null);

            return $updated->gte($since);
        });
    }

    /**
     * Filter by active repositories (updated in last N days)
     */
    public function active(int $days = 30): self
    {
        return $this->updatedSince(Carbon::now()->subDays($days)->toISOString());
    }

    /**
     * Filter by search term in name or description
     */
    public function search(string $term): self
    {
        $term = strtolower($term);

        return $this->filter(function ($item) use ($term) {
            $name = strtolower($item['name'] ?? '');
            $fullName = strtolower($item['full_name'] ?? '');
            $description = strtolower($item['description'] ?? '');
            $title = strtolower($item['title'] ?? ''); // for issues

            return str_contains($name, $term) ||
                   str_contains($fullName, $term) ||
                   str_contains($description, $term) ||
                   str_contains($title, $term);
        });
    }

    /**
     * Filter by issue state (issues only)
     */
    public function state(string $state): self
    {
        return $this->filter(function ($item) use ($state) {
            return strtolower($item['state'] ?? '') === strtolower($state);
        });
    }

    /**
     * Filter by assignee (issues only)
     */
    public function assignedTo(string $username): self
    {
        return $this->filter(function ($item) use ($username) {
            $assignee = $item['assignee']['login'] ?? null;

            return $assignee === $username;
        });
    }

    /**
     * Filter by labels (issues only)
     */
    public function hasLabel(string $label): self
    {
        return $this->filter(function ($item) use ($label) {
            $labels = collect($item['labels'] ?? []);

            return $labels->pluck('name')->contains($label);
        });
    }

    /**
     * Filter by multiple labels (issues only)
     */
    public function hasLabels(array $labels): self
    {
        return $this->filter(function ($item) use ($labels) {
            $itemLabels = collect($item['labels'] ?? [])->pluck('name')->toArray();

            return ! empty(array_intersect($labels, $itemLabels));
        });
    }

    /**
     * Sort by various criteria
     */
    public function sortBy(string $field, string $direction = 'desc'): self
    {
        $ascending = strtolower($direction) === 'asc';

        return new self(
            $this->data->sortBy($field, SORT_REGULAR, ! $ascending)->values()->toArray(),
            $this->type
        );
    }

    /**
     * Limit results
     */
    public function limit(int $limit): self
    {
        return new self(
            $this->data->take($limit)->toArray(),
            $this->type
        );
    }

    /**
     * Natural language query parsing
     */
    public function query(string $query): self
    {
        $query = strtolower(trim($query));
        $result = $this;

        // Language patterns
        if (preg_match('/\b(php|python|javascript|js|typescript|ts|go|rust|java|ruby|c\+\+|cpp|c#|csharp)\b/', $query, $matches)) {
            $lang = $matches[1];
            // Normalize language names
            $lang = match ($lang) {
                'js' => 'javascript',
                'ts' => 'typescript',
                'cpp' => 'c++',
                'csharp' => 'c#',
                default => $lang
            };
            $result = $result->language($lang);
        }

        // Star patterns
        if (preg_match('/(\d+)\+?\s*stars?/', $query, $matches)) {
            $result = $result->minStars((int) $matches[1]);
        }

        if (preg_match('/over\s+(\d+)\s*stars?/', $query, $matches)) {
            $result = $result->minStars((int) $matches[1]);
        }

        // Visibility patterns
        if (str_contains($query, 'private')) {
            $result = $result->visibility('private');
        } elseif (str_contains($query, 'public')) {
            $result = $result->visibility('public');
        }

        // Activity patterns
        if (preg_match('/active|recent|updated/', $query)) {
            if (preg_match('/last\s+(\d+)\s+days?/', $query, $matches)) {
                $result = $result->active((int) $matches[1]);
            } else {
                $result = $result->active(30);
            }
        }

        // State patterns (for issues)
        if (str_contains($query, 'open')) {
            $result = $result->state('open');
        } elseif (str_contains($query, 'closed')) {
            $result = $result->state('closed');
        }

        // Search patterns
        if (preg_match('/search\s+["\']([^"\']+)["\']/', $query, $matches)) {
            $result = $result->search($matches[1]);
        } elseif (preg_match('/contains?\s+["\']([^"\']+)["\']/', $query, $matches)) {
            $result = $result->search($matches[1]);
        }

        return $result;
    }

    /**
     * Get the filtered data
     */
    public function get(): array
    {
        return $this->data->toArray();
    }

    /**
     * Get as collection
     */
    public function collect(): Collection
    {
        return $this->data;
    }

    /**
     * Count results
     */
    public function count(): int
    {
        return $this->data->count();
    }

    /**
     * Check if results are empty
     */
    public function isEmpty(): bool
    {
        return $this->data->isEmpty();
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        return $this->data->first();
    }

    /**
     * Apply custom filter
     */
    private function filter(callable $callback): self
    {
        return new self(
            $this->data->filter($callback)->values()->toArray(),
            $this->type
        );
    }

    /**
     * Get statistics about the data
     */
    public function stats(): array
    {
        if ($this->type === 'repository') {
            return $this->getRepositoryStats();
        } elseif ($this->type === 'issue') {
            return $this->getIssueStats();
        }

        return ['total' => $this->count()];
    }

    private function getRepositoryStats(): array
    {
        return [
            'total' => $this->count(),
            'languages' => $this->data->pluck('language')->filter()->countBy()->toArray(),
            'private' => $this->data->where('private', true)->count(),
            'public' => $this->data->where('private', false)->count(),
            'total_stars' => $this->data->sum('stargazers_count'),
            'average_stars' => $this->count() > 0 ? round($this->data->avg('stargazers_count'), 1) : 0,
        ];
    }

    private function getIssueStats(): array
    {
        return [
            'total' => $this->count(),
            'open' => $this->data->where('state', 'open')->count(),
            'closed' => $this->data->where('state', 'closed')->count(),
            'assigned' => $this->data->whereNotNull('assignee')->count(),
            'unassigned' => $this->data->whereNull('assignee')->count(),
        ];
    }
}
