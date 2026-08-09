<?php

namespace App\Support;

final class SeededDraw
{
    public const ALGORITHM_VERSION = 'hmac-sha256-rank-v1';

    /**
     * @param  list<int>  $entryIds
     * @return list<int>
     */
    public static function shuffle(array $entryIds, string $seed): array
    {
        $entryIds = array_values(array_map('intval', $entryIds));
        sort($entryIds, SORT_NUMERIC);

        usort($entryIds, static function (int $left, int $right) use ($seed): int {
            $leftRank = hash_hmac('sha256', self::ALGORITHM_VERSION.'|'.$left, $seed);
            $rightRank = hash_hmac('sha256', self::ALGORITHM_VERSION.'|'.$right, $seed);

            return $leftRank <=> $rightRank ?: $left <=> $right;
        });

        return $entryIds;
    }
}
