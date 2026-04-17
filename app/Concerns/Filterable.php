<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Filterable
{
    #[Scope]
    public function pick(Builder $query, ?string $keyword, string $column = 'id', string $operator = '='): void
    {
        $query->where($column, $operator, $keyword);
    }

    #[Scope]
    public function filterWhere(Builder $query, string $column, ?string $keyword, string $operator = '='): void
    {
        if ($keyword != '') {
            $query->where($column, $operator, $keyword);
        }
    }

    #[Scope]
    public function orFilterWhere(Builder $query, string $column, ?string $keyword, string $operator = '='): void
    {
        if ($keyword != '') {
            $query->orWhere($column, $operator, $keyword);
        }
    }

    #[Scope]
    public function filterLike(Builder $query, string $column, ?string $keyword): void
    {
        if ($keyword != '') {
            $query->where(DB::raw("LOWER($column)"), 'like', '%'.strtolower($keyword).'%');
        }
    }

    #[Scope]
    public function orFilterLike(Builder $query, string $column, ?string $keyword): void
    {
        if ($keyword != '') {
            $query->orWhere(DB::raw("LOWER($column)"), 'like', '%'.strtolower($keyword).'%');
        }
    }

    #[Scope]
    public function isActive(Builder $query): void
    {
        $query->where($this->getTable().'.is_active', true);
    }

    #[Scope]
    public function notActive(Builder $query): void
    {
        $query->where($this->getTable().'.is_active', false);
    }

    #[Scope]
    public function active(Builder $query, ?string $keyword, string $column = 'is_active'): void
    {
        if ($keyword != '') {
            $keyword = $keyword == 'active' ? true : false;
            $query->where($column, $keyword);
        }
    }

    #[Scope]
    public function filterBetween(Builder $query, string $column, string $start, string $end): void
    {
        if (($start != '') and ($end != '')) {
            $query->whereBetween($column, [$start, $end]);
        }
    }

    #[Scope]
    public function orFilterBetween(Builder $query, string $column, string $start, string $end): void
    {
        if (($start != '') and ($end != '')) {
            $query->OrWhereBetween($column, [$start, $end]);
        }
    }

    #[Scope]
    public function filterWhereHas($query, $relation, $related): void
    {
        if (! empty($related->id)) {
            $query->whereHas(
                $relation,
                function ($query) use ($related) {
                    $query->where('id', '=', $related->id);
                }
            );
        }
    }

    #[Scope]
    public function orFilterWhereHas($query, $relation, $related): void
    {
        if (! empty($related->id)) {
            $query->OrWhereHas(
                $relation,
                function ($query) use ($related) {
                    $query->where('id', '=', $related->id);
                }
            );
        }
    }

    #[Scope]
    public function whereDateBetween(Builder $query, string $column, string $startDate, string $endDate)
    {
        $query->whereBetween(DB::raw($column), [$startDate, $endDate]);
    }
}
