<?php

namespace App\Support;

/**
 * Two-letter monogram for an organisation name, skipping filler words, so
 * "University of Professional Studies" becomes "UP" rather than "UO".
 */
final class Monogram
{
    private const SKIP = ['of', 'the', 'and', 'for', 'in', 'at', 'on', 'a', 'an', '&'];

    public static function for(string $name): string
    {
        $words = collect(preg_split('/\s+/', trim((string) preg_replace('/[^\pL\s]/u', ' ', $name))))
            ->filter(fn (?string $word) => $word !== null && $word !== '');
        $meaningful = $words->reject(fn (string $word) => in_array(mb_strtolower($word), self::SKIP, true));

        $initials = ($meaningful->isNotEmpty() ? $meaningful : $words)
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');

        return $initials !== '' ? $initials : 'O';
    }
}
