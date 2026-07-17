<?php

require_once __DIR__ . '/BurgleBrosFloorPlan.class.php';

/*
 * BurgleBrosPathfinder: the guard's route across one floor. Rules p.8, 2nd ed.
 * Mark III v2.05: "The Guard always takes the shortest possible path to the
 * destination. If more than one path is equally short, the Guard will take the
 * path that is most clockwise when the shortest paths are viewed together."
 * Pure geometry over a BurgleBrosFloorPlan (cells as documented on
 * BurgleBrosGrid), no database or framework access, so it is unit-testable
 * locally (see misc/tests/).
 *
 * How it works: a BFS from the destination labels every cell with its walking
 * distance. The steps that decrease that distance by 1 are exactly the steps
 * that lie on some shortest path, so the guard walks from the start always
 * stepping "downhill"; wherever more than one downhill step exists (a fork
 * among the tied shortest paths), the most clockwise branch wins. Clockwise is
 * judged relative to the nearest cell where the diverging branches meet again
 * — not to the destination, which can sit far off to one side and bias the
 * comparison.
 */
class BurgleBrosPathfinder
{
	/*
	 * The tie-break table. Outer key: bearing from the fork cell to the anchor
	 * (the reconvergence cell) — a one- or two-letter compass direction, letters
	 * always in alphabetical order (D=down, L=left, R=right, U=up). Inner key:
	 * the two branch bearings, also alphabetically. Value: the branch that is
	 * more clockwise. There is no entry for branches directly toward and away
	 * from the anchor ('DU' under 'U'/'D', 'LR' under 'L'/'R'); clockwise()
	 * falls back to its second branch then.
	 */
	public const CLOCKWISE_MAPPINGS = [
		'LU' => [
			'DL' => 'D',
			'LU' => 'L',
			'RU' => 'R',
			'DR' => 'D',
			'DU' => 'D',
			'LR' => 'L',
		],
		'DL' => [
			'DL' => 'D',
			'LU' => 'L',
			'RU' => 'R',
			'DR' => 'D',
			'DU' => 'D',
			'LR' => 'R',
		],
		'RU' => [
			'DL' => 'L',
			'LU' => 'U',
			'RU' => 'U',
			'DR' => 'R',
			'DU' => 'U',
			'LR' => 'L',
		],
		'DR' => [
			'DL' => 'L',
			'LU' => 'U',
			'RU' => 'U',
			'DR' => 'R',
			'DU' => 'U',
			'LR' => 'R',
		],
		'U' => [
			'DL' => 'L',
			'LU' => 'L',
			'RU' => 'U',
			'DR' => 'D',
			'LR' => 'L',
		],
		'D' => [
			'DL' => 'D',
			'LU' => 'U',
			'RU' => 'R',
			'DR' => 'R',
			'LR' => 'R',
		],
		'L' => [
			'DL' => 'D',
			'LU' => 'L',
			'RU' => 'R',
			'DR' => 'D',
			'DU' => 'D',
		],
		'R' => [
			'DL' => 'L',
			'LU' => 'U',
			'RU' => 'U',
			'DR' => 'R',
			'DU' => 'U',
		],
	];

	public function __construct(
		private readonly BurgleBrosFloorPlan $plan,
		private readonly int $floor,
	) {}

	/** @return list<int> cells from $start to $end, both included */
	public function shortestPathClockwise(int $start, int $end): array {
		$dist = $this->distancesFrom($end);
		if (!isset($dist[$start])) {
			throw new RuntimeException("no path from cell $start to cell $end on floor {$this->floor}");
		}
		$path = array($start);
		$current = $start;
		while ($current !== $end) {
			$branches = array();
			foreach ($this->openNeighbors($current) as $next) {
				if ($dist[$next] === $dist[$current] - 1) {
					$branches[] = $next;
				}
			}
			sort($branches);
			$next = array_shift($branches);
			foreach ($branches as $branch) {
				$next = $this->clockwise($current, $this->reconvergence($dist, $next, $branch), $next, $branch);
			}
			$path[] = $next;
			$current = $next;
		}
		return $path;
	}

	/** @return array<int, int> cell => walking distance to $cell, for every reachable cell */
	private function distancesFrom(int $cell): array {
		$dist = array($cell => 0);
		$queue = array($cell);
		for ($i = 0; $i < count($queue); $i++) {
			foreach ($this->openNeighbors($queue[$i]) as $next) {
				if (!isset($dist[$next])) {
					$dist[$next] = $dist[$queue[$i]] + 1;
					$queue[] = $next;
				}
			}
		}
		return $dist;
	}

	/** @return list<int> cells sharing an unwalled edge with $cell */
	private function openNeighbors(int $cell): array {
		$size = $this->plan->size;
		$candidates = array();
		if ($this->plan->colOf($cell) > 0) $candidates[] = $cell - 1;
		if ($this->plan->colOf($cell) < $size - 1) $candidates[] = $cell + 1;
		if ($this->plan->rowOf($cell) > 0) $candidates[] = $cell - $size;
		if ($this->plan->rowOf($cell) < $size - 1) $candidates[] = $cell + $size;
		return array_values(array_filter(
			$candidates,
			fn($next) => !$this->plan->wallBetween($this->floor, $cell, $next)
		));
	}

	/*
	 * The nearest cell that shortest paths through both branches reach again —
	 * the anchor the clockwise comparison sights toward. Both sets contain the
	 * destination (distance 0), so a common cell always exists; equally near
	 * candidates tie-break to the lowest cell for determinism.
	 */
	private function reconvergence(array $dist, int $a, int $b): int {
		$common = array_intersect_key($this->downhillFrom($dist, $a), $this->downhillFrom($dist, $b));
		$best = null;
		foreach (array_keys($common) as $cell) {
			if ($best === null || $dist[$cell] > $dist[$best] || ($dist[$cell] === $dist[$best] && $cell < $best)) {
				$best = $cell;
			}
		}
		return $best;
	}

	/** @return array<int, true> cells on some shortest path continuing from $cell (exclusive) */
	private function downhillFrom(array $dist, int $cell): array {
		$seen = array();
		$stack = array($cell);
		while ($stack) {
			$current = array_pop($stack);
			foreach ($this->openNeighbors($current) as $next) {
				if ($dist[$next] === $dist[$current] - 1 && !isset($seen[$next])) {
					$seen[$next] = true;
					$stack[] = $next;
				}
			}
		}
		return $seen;
	}

	// Which of the adjacent cells $left / $right is more clockwise from
	// $current, sighting toward $anchor.
	private function clockwise(int $current, int $anchor, int $left, int $right): int {
		$ldir = $this->bearing($current, $left);
		$rdir = $this->bearing($current, $right);
		$pair = $ldir < $rdir ? $ldir . $rdir : $rdir . $ldir;
		$winner = self::CLOCKWISE_MAPPINGS[$this->bearing($current, $anchor)][$pair] ?? '';
		return $winner === $ldir ? $left : $right;
	}

	// Compass bearing from $from to $to: a subset of D, L, R, U in alphabetical
	// order ('' when the cells coincide, two letters on diagonals).
	private function bearing(int $from, int $to): string {
		$dx = $this->plan->colOf($from) - $this->plan->colOf($to);
		$dy = $this->plan->rowOf($to) - $this->plan->rowOf($from);
		$dirs = '';
		if ($dy > 0) $dirs .= 'D';
		if ($dx > 0) $dirs .= 'L';
		if ($dx < 0) $dirs .= 'R';
		if ($dy < 0) $dirs .= 'U';
		return $dirs;
	}
}
