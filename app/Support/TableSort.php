<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Whitelisted column sort from `?sort=` + `?dir=` query parameters.
 */
final class TableSort
{
    public function __construct(
        public readonly string $column,
        public readonly string $direction,
    ) {}

    /**
     * @param  array<string, string|\Closure>  $allowed  map of request key → column or callback
     */
    public static function from(Request $request, array $allowed, string $default, string $defaultDir = 'asc'): self
    {
        $column = $request->string('sort')->toString();
        if (! array_key_exists($column, $allowed)) {
            $column = $default;
        }

        $direction = $request->string('dir')->toString();
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDir;
        }

        return new self($column, $direction);
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, string|\Closure>  $allowed
     * @return Builder<*>
     */
    public function apply(Builder $query, array $allowed): Builder
    {
        $order = $allowed[$this->column] ?? null;

        if ($order instanceof \Closure) {
            $order($query, $this->direction);

            return $query;
        }

        if (is_string($order)) {
            $query->orderBy($order, $this->direction);
        }

        return $query;
    }

    public function is(string $column): bool
    {
        return $this->column === $column;
    }

    public function nextDirection(string $column): string
    {
        return $this->is($column) && $this->direction === 'asc' ? 'desc' : 'asc';
    }
}
