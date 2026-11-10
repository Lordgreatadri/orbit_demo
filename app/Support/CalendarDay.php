<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Calendar-day predicates that stay on the column.
 *
 * SQLite stores a date cast as a midnight timestamp. A bare equality or
 * `column <= 'Y-m-d'` then misses the row. A half-open range matches both
 * that timestamp and a real DATE column, and it can still use an index.
 */
final class CalendarDay
{
    public static function constrain(Builder $query, string $column, string $date): Builder
    {
        return $query->where($column, '>=', $date)->where($column, '<', self::following($date));
    }

    /**
     * @param  list<string>  $dates
     */
    public static function constrainAny(Builder $query, string $column, array $dates): Builder
    {
        $dates = array_values(array_unique($dates));

        return $query->where(function (Builder $outer) use ($column, $dates): void {
            foreach ($dates as $date) {
                $outer->orWhere(function (Builder $inner) use ($column, $date): void {
                    self::constrain($inner, $column, $date);
                });
            }
        });
    }

    public static function onOrBefore(Builder $query, string $column, string $date): Builder
    {
        return $query->where($column, '<', self::following($date));
    }

    public static function following(string $date): string
    {
        return Carbon::parse($date)->addDay()->toDateString();
    }
}
