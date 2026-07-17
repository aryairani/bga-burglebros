<?php

/*
 * BurgleBrosGrid: geometry of one square floor of the building — everything
 * that is a function of the grid size alone, independent of which walls the
 * current game has. BurgleBrosFloorPlan extends this with the wall list; call
 * sites that only need cell arithmetic construct a bare grid and skip loading
 * walls from the database. Pure geometry, no database or framework access, so
 * it is unit-testable locally (see misc/tests/).
 *
 * Vocabulary:
 *   size      Tiles per side of a (square) floor: 4, or 5 in the Fort Knox Job.
 *   cell      A tile position on a floor, numbered 0..size²-1 in reading order
 *             (left to right, top to bottom): row = cell / size, col = cell % size.
 *   wall position  Walls stand in the size-1 gaps between cells of a row or
 *             column. A vertical wall (vertical=1) at position p stands between
 *             cell (row, col) and (row, col+1) where row = p / (size-1) and
 *             col = p % (size-1); a horizontal wall (vertical=0) stands between
 *             (row, col) and (row+1, col) where row = p % (size-1) and
 *             col = p / (size-1). cellsSeparatedBy() decodes this.
 */

class BurgleBrosGrid
{
	// No strict_types here: cell and wall values arrive from the DB as numeric
	// strings and rely on PHP's coercive mode to become int parameters.
	public function __construct(public readonly int $size) {}

	public function rowOf(int $cell): int {
		return intdiv($cell, $this->size);
	}

	public function colOf(int $cell): int {
		return $cell % $this->size;
	}

	// True when the cell is not on the floor's outer ring.
	public function isInteriorCell(int $cell): bool {
		$row = $this->rowOf($cell);
		$col = $this->colOf($cell);
		return $row > 0 && $row < $this->size - 1
			&& $col > 0 && $col < $this->size - 1;
	}

	public function manhattanDistance(int $cell, int $other_cell): int {
		return abs($this->rowOf($cell) - $this->rowOf($other_cell))
			+ abs($this->colOf($cell) - $this->colOf($other_cell));
	}

	/**
	 * The two cells a wall stands between, lowest first.
	 * @param array{vertical: int|string, position: int|string, ...} $wall
	 * @return array{int, int}
	 */
	public function cellsSeparatedBy(array $wall): array {
		$gaps = $this->size - 1;
		if ($wall['vertical'] == 1) {
			$cell = intdiv((int) $wall['position'], $gaps) * $this->size + $wall['position'] % $gaps;
			return array($cell, $cell + 1);
		} else {
			$cell = ($wall['position'] % $gaps) * $this->size + intdiv((int) $wall['position'], $gaps);
			return array($cell, $cell + $this->size);
		}
	}
}
