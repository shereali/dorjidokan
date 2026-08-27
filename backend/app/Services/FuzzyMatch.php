<?php

namespace App\Services;

/**
 * Lightweight fuzzy matching helpers for voice-agent lookups.
 *
 * Spoken/transliterated names are frequently misheard, so exact-name
 * matching alone misses real customers. These helpers rank candidates by
 * edit distance and common-prefix bonus without pulling in a full search
 * engine — appropriate for per-tenant datasets of thousands of rows.
 */
class FuzzyMatch
{
    /**
     * Levenshtein distance with a bounded cost (stops early above $limit).
     */
    public static function distance(string $a, string $b, int $limit = 3): int
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 0;
        }
        if ($limit <= 0) {
            return 999;
        }
        if (strlen($a) === 0) {
            return strlen($b);
        }
        if (strlen($b) === 0) {
            return strlen($a);
        }
        if (strlen($b) > strlen($a)) {
            [$a,$b] = [$b, $a];
        }
        $row = range(0, strlen($b));
        foreach (str_split($a) as $i => $ca) {
            $previous = $row;
            $row[0] = $i + 1;
            $best = $row[0];
            foreach (str_split($b) as $j => $cb) {
                $cost = $ca === $cb ? 0 : 1;
                $row[$j + 1] = min($previous[$j + 1] + 1, $row[$j] + 1, $previous[$j] + $cost);
                $best = min($best, $row[$j + 1]);
            }
            if ($best > $limit) {
                return $best;
            }
        }

        return $row[strlen($b)];
    }

    /**
     * Score a candidate name against a query. Higher is better.
     */
    public static function score(string $query, string $candidate): int
    {
        $query = self::normalize($query);
        $candidate = self::normalize($candidate);
        if ($candidate === '' || $query === '') {
            return -1;
        }
        if ($candidate === $query) {
            return 1000;
        }
        if (str_starts_with($candidate, $query)) {
            return 800 - abs(strlen($candidate) - strlen($query));
        }
        if (str_contains($candidate, $query)) {
            return 600 - abs(strlen($candidate) - strlen($query));
        }
        $distance = self::distance($query, $candidate, 3);

        return $distance <= 2 ? 400 - ($distance * 100) : -1;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['’', '‘', '`', "'", '"'], '', $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
