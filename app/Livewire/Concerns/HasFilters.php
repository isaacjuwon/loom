<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Computed;

/**
 * Livewire component concern for managing filter state.
 *
 * Works alongside the Filterable model concern (App\Concerns\Filterable),
 * which provides the Eloquent scopes. This trait handles the component side:
 * declaring filter defaults, resetting state, and reporting active filters.
 *
 * ── Setup ────────────────────────────────────────────────────────────────────
 *
 * 1. Add the trait to your Livewire component:
 *
 *      use App\Livewire\Concerns\HasFilters;
 *
 * 2. Declare filter defaults — keys must match public properties on the component:
 *
 *      protected array $filterDefaults = [
 *          'filterSearch'   => '',
 *          'filterStatus'   => '',
 *          'filterPriority' => '',
 *          'filterAssignee' => null,
 *          'filterOverdue'  => false,
 *      ];
 *
 * 3. Declare the matching public properties:
 *
 *      public string $filterSearch   = '';
 *      public string $filterStatus   = '';
 *      public string $filterPriority = '';
 *      public ?int   $filterAssignee = null;
 *      public bool   $filterOverdue  = false;
 *
 * 4. Override $computedCacheKeys to list the #[Computed] properties that
 *    should be cleared when filters change (defaults to ['items']):
 *
 *      protected array $computedCacheKeys = ['tasks'];
 *
 * ── Usage in #[Computed] ─────────────────────────────────────────────────────
 *
 *      #[Computed]
 *      public function tasks()
 *      {
 *          return $this->project->tasks()
 *              ->filterWhere('status', $this->filterStatus)
 *              ->filterWhere('priority', $this->filterPriority)
 *              ->ofAssignee($this->filterAssignee)
 *              ->when($this->filterOverdue, fn ($q) => $q->overdue())
 *              ->filterLike('title', $this->filterSearch)
 *              ->get();
 *      }
 */
trait HasFilters
{
    /**
     * Cache keys of #[Computed] properties to unset when filters are reset.
     * Override in the component to match your computed property names.
     *
     * @var array<int, string>
     */
    protected array $computedCacheKeys = ['items'];

    /**
     * Reset all filter properties to their defaults and clear computed cache.
     */
    public function resetFilters(): void
    {
        foreach ($this->filterDefaults as $property => $default) {
            $this->$property = $default;
        }

        $this->clearComputedCache();
    }

    /**
     * Whether any filter is currently non-default.
     */
    #[Computed]
    public function hasActiveFilters(): bool
    {
        foreach ($this->filterDefaults as $property => $default) {
            if ($default !== $this->$property) {
                return true;
            }
        }

        return false;
    }

    /**
     * Number of filters currently active.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        $count = 0;

        foreach ($this->filterDefaults as $property => $default) {
            if ($default !== $this->$property) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Unset all computed cache keys declared in $computedCacheKeys.
     * Called automatically by resetFilters(). Can also be called manually
     * after programmatic filter changes.
     */
    protected function clearComputedCache(): void
    {
        foreach ($this->computedCacheKeys as $key) {
            unset($this->$key);
        }
    }
}
