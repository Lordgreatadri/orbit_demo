<?php

namespace App\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived aggregate cache. The key includes the organisation, the viewer,
 * the perspective, department and location filters, and the date window.
 * A tenant id alone is not enough, because two people in the same organisation
 * can be allowed to see different departments.
 */
final class AnalyticsCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(AnalyticsScope $scope, string $metric, Closure $callback): mixed
    {
        $seconds = (int) config('orbit.analytics_cache_seconds', 60);

        if ($seconds < 1) {
            return $callback();
        }

        return Cache::remember($scope->cacheKey($metric), $seconds, $callback);
    }
}
