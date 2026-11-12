<?php

namespace App\Contracts;

use App\Analytics\AnalyticsScope;
use App\Analytics\Insight;

/**
 * Produces operational observations for a scope the caller has already authorised.
 * An external model, if one is approved later, must implement this contract and
 * must not query tenant tables itself.
 */
interface OperationalInsightProvider
{
    /**
     * @param  array{present: int, late: int, absent: int, on_leave: int, incomplete: int, excused: int, active_staff: int}|null  $today
     * @return list<Insight>
     */
    public function forScope(AnalyticsScope $scope, ?array $today = null): array;
}
