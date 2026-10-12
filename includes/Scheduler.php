<?php

require_once __DIR__ . '/helpers.php';

/**
 * Builds doubles schedules with fair sit-outs and no-repeat partners when possible.
 */
class Scheduler
{
    /**
     * @param int[] $playerIds
     * @return array{slots: array<int, array{active: int[], sitouts: int[], courts: array<int, array{p1:int,p2:int,p3:int,p4:int}>}>, repeated_pairs: array<int, array{a:int,b:int}>, has_repeats: bool}
     */
    public static function generate(array $playerIds, int $courts, float $hours, int $slotMinutes = 15): array
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        sort($playerIds);

        if ($courts < 1) {
            throw new InvalidArgumentException('At least 1 court is required.');
        }
        if (count($playerIds) < 4) {
            throw new InvalidArgumentException('At least 4 players are required.');
        }
        if ($hours <= 0) {
            throw new InvalidArgumentException('Hours must be greater than 0.');
        }

        $slotCount = (int) round(($hours * 60) / $slotMinutes);
        if ($slotCount < 1) {
            throw new InvalidArgumentException('Session must include at least one 15-minute slot.');
        }

        $capacity = $courts * 4;
        if (count($playerIds) < $capacity) {
            // Fewer players than full courts: use as many full courts as possible.
            $courts = intdiv(count($playerIds), 4);
            if ($courts < 1) {
                throw new InvalidArgumentException('Need at least 4 players for one court.');
            }
            $capacity = $courts * 4;
        }

        $gamesPlayed = array_fill_keys($playerIds, 0);
        $partnerHistory = []; // partner_key => true
        $slots = [];
        $allRepeated = [];

        for ($s = 0; $s < $slotCount; $s++) {
            $active = self::pickActive($playerIds, $gamesPlayed, $capacity);
            $sitouts = array_values(array_diff($playerIds, $active));
            $result = self::bestPairing($active, $partnerHistory, $courts);

            foreach ($result['courts'] as $court) {
                $pairs = [
                    [$court['p1'], $court['p2']],
                    [$court['p3'], $court['p4']],
                ];
                foreach ($pairs as [$a, $b]) {
                    $key = partner_key($a, $b);
                    if (isset($partnerHistory[$key])) {
                        $allRepeated[$key] = ['a' => min($a, $b), 'b' => max($a, $b)];
                    }
                    $partnerHistory[$key] = true;
                }
                foreach ([$court['p1'], $court['p2'], $court['p3'], $court['p4']] as $pid) {
                    $gamesPlayed[$pid]++;
                }
            }

            $slots[] = [
                'active' => $active,
                'sitouts' => $sitouts,
                'courts' => $result['courts'],
            ];
        }

        return [
            'slots' => $slots,
            'repeated_pairs' => array_values($allRepeated),
            'has_repeats' => count($allRepeated) > 0,
            'courts_used' => $courts,
        ];
    }

    /**
     * @param int[] $playerIds
     * @param array<int,int> $gamesPlayed
     * @return int[]
     */
    private static function pickActive(array $playerIds, array $gamesPlayed, int $capacity): array
    {
        $sorted = $playerIds;
        usort($sorted, static function (int $a, int $b) use ($gamesPlayed): int {
            $cmp = ($gamesPlayed[$a] ?? 0) <=> ($gamesPlayed[$b] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return random_int(0, 1) === 0 ? -1 : 1;
        });

        return array_slice($sorted, 0, $capacity);
    }

    /**
     * @param int[] $active
     * @param array<string,bool> $partnerHistory
     * @return array{courts: array<int, array{p1:int,p2:int,p3:int,p4:int}>, repeat_count: int}
     */
    private static function bestPairing(array $active, array $partnerHistory, int $courts): array
    {
        $n = count($active);
        $best = null;
        $bestScore = PHP_INT_MAX;

        // Try several random orders + one deterministic order.
        $attempts = min(80, max(20, $n * 4));
        for ($t = 0; $t < $attempts; $t++) {
            $order = $active;
            if ($t === 0) {
                sort($order);
            } else {
                shuffle($order);
            }
            $candidate = self::pairFromOrder($order, $courts, $partnerHistory);
            if ($candidate['repeat_count'] < $bestScore) {
                $bestScore = $candidate['repeat_count'];
                $best = $candidate;
                if ($bestScore === 0) {
                    break;
                }
            }
        }

        // Exhaustive search for small groups (perfect when n <= 8 or so).
        if ($bestScore > 0 && $n <= 8) {
            $exhaustive = self::exhaustiveBest($active, $partnerHistory, $courts);
            if ($exhaustive !== null && $exhaustive['repeat_count'] < $bestScore) {
                $best = $exhaustive;
                $bestScore = $exhaustive['repeat_count'];
            }
        }

        return $best ?? ['courts' => [], 'repeat_count' => 0];
    }

    /**
     * @param int[] $order
     * @param array<string,bool> $partnerHistory
     * @return array{courts: array<int, array{p1:int,p2:int,p3:int,p4:int}>, repeat_count: int}
     */
    private static function pairFromOrder(array $order, int $courts, array $partnerHistory): array
    {
        // Build all possible perfect matchings of pairs, then group pairs into courts.
        // Practical approach: greedily form pairs minimizing new repeats, then assign opposing pairs.
        $remaining = $order;
        $pairs = [];
        $repeatCount = 0;

        while (count($remaining) >= 2) {
            $bestI = 0;
            $bestJ = 1;
            $bestCost = PHP_INT_MAX;
            $m = count($remaining);
            for ($i = 0; $i < $m; $i++) {
                for ($j = $i + 1; $j < $m; $j++) {
                    $key = partner_key($remaining[$i], $remaining[$j]);
                    $cost = isset($partnerHistory[$key]) ? 1000 : 0;
                    // Prefer pairing early players (lower games already reflected in order).
                    $cost += $i + $j;
                    if ($cost < $bestCost) {
                        $bestCost = $cost;
                        $bestI = $i;
                        $bestJ = $j;
                    }
                }
            }
            $a = $remaining[$bestI];
            $b = $remaining[$bestJ];
            $key = partner_key($a, $b);
            if (isset($partnerHistory[$key])) {
                $repeatCount++;
            }
            $pairs[] = [$a, $b];
            array_splice($remaining, max($bestI, $bestJ), 1);
            array_splice($remaining, min($bestI, $bestJ), 1);
        }

        // Pair of pairs → courts; try to avoid same four repeating as partners already handled.
        $courtList = [];
        $pairCount = count($pairs);
        $used = array_fill(0, $pairCount, false);
        for ($c = 0; $c < $courts; $c++) {
            $first = -1;
            for ($i = 0; $i < $pairCount; $i++) {
                if (!$used[$i]) {
                    $first = $i;
                    break;
                }
            }
            $second = -1;
            for ($j = $first + 1; $j < $pairCount; $j++) {
                if (!$used[$j]) {
                    $second = $j;
                    break;
                }
            }
            if ($first < 0 || $second < 0) {
                break;
            }
            $used[$first] = true;
            $used[$second] = true;
            $courtList[] = [
                'p1' => $pairs[$first][0],
                'p2' => $pairs[$first][1],
                'p3' => $pairs[$second][0],
                'p4' => $pairs[$second][1],
            ];
        }

        return ['courts' => $courtList, 'repeat_count' => $repeatCount];
    }

    /**
     * @param int[] $active
     * @param array<string,bool> $partnerHistory
     * @return array{courts: array<int, array{p1:int,p2:int,p3:int,p4:int}>, repeat_count: int}|null
     */
    private static function exhaustiveBest(array $active, array $partnerHistory, int $courts): ?array
    {
        $ids = array_values($active);
        $n = count($ids);
        if ($n % 2 !== 0 || $n < 4) {
            return null;
        }

        $best = null;
        $bestScore = PHP_INT_MAX;
        $pairings = self::allPerfectPairings($ids);

        foreach ($pairings as $pairs) {
            if (count($pairs) < $courts * 2) {
                continue;
            }
            // Arrange pairs into courts: take first 2*courts pairs in combinations of opposing.
            $pairSubset = array_slice($pairs, 0, $courts * 2);
            $repeat = 0;
            foreach ($pairSubset as [$a, $b]) {
                if (isset($partnerHistory[partner_key($a, $b)])) {
                    $repeat++;
                }
            }
            $courtsOut = [];
            for ($c = 0; $c < $courts; $c++) {
                $pA = $pairSubset[$c * 2];
                $pB = $pairSubset[$c * 2 + 1];
                $courtsOut[] = [
                    'p1' => $pA[0],
                    'p2' => $pA[1],
                    'p3' => $pB[0],
                    'p4' => $pB[1],
                ];
            }
            if ($repeat < $bestScore) {
                $bestScore = $repeat;
                $best = ['courts' => $courtsOut, 'repeat_count' => $repeat];
                if ($bestScore === 0) {
                    return $best;
                }
            }
        }

        return $best;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<int, array{0:int,1:int}>>
     */
    private static function allPerfectPairings(array $ids): array
    {
        $ids = array_values($ids);
        if (count($ids) === 0) {
            return [[]];
        }
        if (count($ids) % 2 !== 0) {
            return [];
        }

        $first = $ids[0];
        $rest = array_slice($ids, 1);
        $results = [];
        $m = count($rest);
        for ($i = 0; $i < $m; $i++) {
            $partner = $rest[$i];
            $remaining = array_merge(array_slice($rest, 0, $i), array_slice($rest, $i + 1));
            foreach (self::allPerfectPairings($remaining) as $sub) {
                $results[] = array_merge([[$first, $partner]], $sub);
            }
        }
        return $results;
    }
}
