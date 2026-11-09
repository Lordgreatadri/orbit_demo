<?php

namespace App\Workforce;

final class Geofence
{
    public static function distanceMetres(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earth = 6_371_000;
        $lat1 = deg2rad($fromLat);
        $lat2 = deg2rad($toLat);
        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
