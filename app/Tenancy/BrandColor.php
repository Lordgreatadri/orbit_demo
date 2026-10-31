<?php

namespace App\Tenancy;

use Illuminate\Validation\ValidationException;

/**
 * Safe hex colour helpers. Outputs only #RRGGBB tokens — never raw CSS.
 */
final class BrandColor
{
    public static function normalize(string $value): string
    {
        $hex = strtoupper(ltrim(trim($value), '#'));

        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            throw ValidationException::withMessages([
                'color' => __('Use a six-digit hex colour such as #0F766E.'),
            ]);
        }

        return '#'.$hex;
    }

    public static function try(?string $value, string $fallback): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        try {
            return self::normalize($value);
        } catch (ValidationException) {
            return $fallback;
        }
    }

    /**
     * Black or white ink that meets a 4.5:1 contrast ratio against $hex.
     */
    public static function ink(string $hex): string
    {
        return self::relativeLuminance($hex) > 0.45 ? '#16212B' : '#FFFFFF';
    }

    public static function hover(string $hex): string
    {
        return self::mix($hex, '#000000', 0.18);
    }

    public static function soft(string $hex): string
    {
        return self::mix($hex, '#FFFFFF', 0.88);
    }

    public static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function mix(string $from, string $to, float $weight): string
    {
        [$fr, $fg, $fb] = self::rgb($from);
        [$tr, $tg, $tb] = self::rgb($to);

        $r = (int) round($fr * (1 - $weight) + $tr * $weight);
        $g = (int) round($fg * (1 - $weight) + $tg * $weight);
        $b = (int) round($fb * (1 - $weight) + $tb * $weight);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
