<?php

/*
 * BurgleBrosWallLayouts: the default (rulebook) wall layouts, drawn as ASCII
 * floor plans, and the parser that turns a plan into the
 * {vertical, horizontal, shaft?} wall-position format used by the `wall` DB
 * table (see BurgleBrosFloorPlan for that encoding). Pure PHP, no framework
 * access; misc/tests/WallLayoutTest.php validates the plans — run it before
 * deploying any change to them.
 *
 * Plan format: 2*size+1 lines on a 4-character lattice.
 *   line 2r,   char 4c         `+` (always)
 *   line 2r,   chars 4c+1..3   `---` if a wall stands between cells (r-1,c)
 *                              and (r,c), spaces if open
 *   line 2r+1, char 4c         `|` if a wall stands between cells (r,c-1)
 *                              and (r,c), space if open
 *   line 2r+1, chars 4c+1..3   cell (r,c): `###` for the Fort Knox empty
 *                              space ("shaft"), spaces otherwise
 * The border must be fully walled; border walls are implicit in the game and
 * not emitted as positions.
 */

final class BurgleBrosWallLayouts
{
	private const THE_BANK_JOB = [
		1 => <<<'PLAN'
			+---+---+---+---+
			|   |           |
			+   +   +---+   +
			|           |   |
			+---+---+   +   +
			|               |
			+   +   +   +---+
			|   |   |       |
			+---+---+---+---+
			PLAN,
		2 => <<<'PLAN'
			+---+---+---+---+
			|   |   |   |   |
			+   +   +   +   +
			|               |
			+   +---+---+   +
			|               |
			+   +   +   +   +
			|   |   |   |   |
			+---+---+---+---+
			PLAN,
		3 => <<<'PLAN'
			+---+---+---+---+
			|               |
			+   +   +---+   +
			|   |       |   |
			+---+   +---+   +
			|   |   |       |
			+   +   +   +   +
			|           |   |
			+---+---+---+---+
			PLAN,
	];

	private const THE_FORT_KNOX_JOB = [
		1 => <<<'PLAN'
			+---+---+---+---+---+
			|           |   |   |
			+   +   +---+   +   +
			|   |           |   |
			+---+   +---+   +   +
			|       |###|       |
			+   +---+---+   +---+
			|                   |
			+---+   +---+---+   +
			|           |       |
			+---+---+---+---+---+
			PLAN,
		2 => <<<'PLAN'
			+---+---+---+---+---+
			|       |   |       |
			+---+   +   +   +   +
			|               |   |
			+   +   +---+   +---+
			|   |   |###|       |
			+   +---+---+   +---+
			|           |       |
			+   +   +   +---+   +
			|   |   |           |
			+---+---+---+---+---+
			PLAN,
	];

	/* Beginner's Game: The Office Job — Rules p.11, 2nd ed. Mark III v2.05. */
	private const THE_OFFICE_JOB = [
		1 => <<<'PLAN'
			+---+---+---+---+
			|   |   |       |
			+   +   +   +---+
			|   |       |   |
			+   +   +   +   +
			|       |       |
			+   +---+   +---+
			|               |
			+---+---+---+---+
			PLAN,
		2 => <<<'PLAN'
			+---+---+---+---+
			|               |
			+   +   +   +---+
			|   |   |   |   |
			+---+   +   +   +
			|   |   |       |
			+   +   +   +---+
			|               |
			+---+---+---+---+
			PLAN,
	];

	/**
	 * The Bank Job layouts, parsed: floor => walls.
	 * @return array<int, array{vertical: int[], horizontal: int[], shaft?: int}>
	 */
	public static function bankJob(): array {
		static $cache = null;
		return $cache ??= array_map(self::parse(...), self::THE_BANK_JOB);
	}

	/**
	 * The Fort Knox Job layouts, parsed: floor => walls.
	 * @return array<int, array{vertical: int[], horizontal: int[], shaft?: int}>
	 */
	public static function fortKnox(): array {
		static $cache = null;
		return $cache ??= array_map(self::parse(...), self::THE_FORT_KNOX_JOB);
	}

	/**
	 * The Office Job beginner layouts, parsed: floor => walls.
	 * @return array<int, array{vertical: int[], horizontal: int[], shaft?: int}>
	 */
	public static function officeJob(): array {
		static $cache = null;
		return $cache ??= array_map(self::parse(...), self::THE_OFFICE_JOB);
	}

	/**
	 * @throws InvalidArgumentException if the plan is not a well-formed grid
	 * @return array{vertical: int[], horizontal: int[], shaft?: int}
	 */
	public static function parse(string $plan): array {
		$lines = explode("\n", $plan);
		$line_count = count($lines);
		if ($line_count < 5 || $line_count % 2 === 0)
			throw new InvalidArgumentException("expected 2*size+1 (>= 5) lines, got $line_count");
		$size = intdiv($line_count, 2);
		$gaps = $size - 1;
		$width = 4 * $size + 1;
		$vertical = [];
		$horizontal = [];
		$shaft = null;
		foreach ($lines as $l => $line) {
			if (strlen($line) !== $width)
				throw new InvalidArgumentException("line $l: expected $width chars, got " . strlen($line));
			$border = $l === 0 || $l === $line_count - 1;
			for ($c = 0; $c <= $size; $c++) {
				$junction = $line[4 * $c];
				if ($l % 2 === 0) {
					if ($junction !== '+')
						throw new InvalidArgumentException("line $l char " . (4 * $c) . ": expected '+', got '$junction'");
				} elseif ($c === 0 || $c === $size) {
					if ($junction !== '|')
						throw new InvalidArgumentException("line $l char " . (4 * $c) . ": border must be '|', got '$junction'");
				} elseif ($junction === '|') {
					$vertical[] = intdiv($l, 2) * $gaps + ($c - 1);
				} elseif ($junction !== ' ') {
					throw new InvalidArgumentException("line $l char " . (4 * $c) . ": expected '|' or ' ', got '$junction'");
				}
				if ($c === $size)
					continue;
				$segment = substr($line, 4 * $c + 1, 3);
				if ($l % 2 === 0) {
					if ($border && $segment !== '---')
						throw new InvalidArgumentException("line $l col $c: border must be '---', got '$segment'");
					if ($segment === '---') {
						if (!$border)
							$horizontal[] = $c * $gaps + (intdiv($l, 2) - 1);
					} elseif ($segment !== '   ') {
						throw new InvalidArgumentException("line $l col $c: expected '---' or spaces, got '$segment'");
					}
				} elseif ($segment === '###') {
					if ($shaft !== null)
						throw new InvalidArgumentException('more than one ### cell');
					$shaft = intdiv($l, 2) * $size + $c;
				} elseif ($segment !== '   ') {
					throw new InvalidArgumentException("line $l col $c: expected '###' or spaces, got '$segment'");
				}
			}
		}
		$walls = ['vertical' => $vertical, 'horizontal' => $horizontal];
		if ($shaft !== null)
			$walls['shaft'] = $shaft;
		return $walls;
	}
}
