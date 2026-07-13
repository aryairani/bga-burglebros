<?php

/*
 * BurgleBrosFloorPlan: the physical layout of the building — the grid size and
 * where the walls are. Pure geometry, no database or framework access, so it is
 * unit-testable locally (see misc/tests/). Construct it from getSquareSize() and
 * getWalls(); everything else is a query against it.
 *
 * Vocabulary:
 *   size      Tiles per side of a (square) floor: 4, or 5 in the Fort Knox Job.
 *   cell      A tile position on a floor, numbered 0..size²-1 in reading order
 *             (left to right, top to bottom): row = cell / size, col = cell % size.
 *   tile      A room card as stored in the `tile` DB table: 'location' is the
 *             string "floorN" (char 5 is the floor number), 'location_arg' is
 *             the cell it occupies. Positions enter this class as
 *             BurgleBrosTilePosition values (fromRow() converts a DB row).
 *   wall      One wall piece as stored in the `wall` DB table: {floor, vertical,
 *             position}. A vertical wall (vertical=1) stands between cell
 *             (row, col) and (row, col+1) where row = position / (size-1) and
 *             col = position % (size-1); a horizontal wall (vertical=0) stands
 *             between (row, col) and (row+1, col) where row = position % (size-1)
 *             and col = position / (size-1).
 *   adjacent  Two tiles on the same floor whose cells share
 *             an edge. Diagonals are never adjacent — Rules p.5, 2nd ed. Mark III
 *             v2.05: "You may not Move diagonally or through Walls."
 *   blocked   A wall stands between the two cells (the "through Walls" half of
 *             the same rule). Movement and peeking need adjacent && !blocked;
 *             special tiles and abilities relax this elsewhere.
 */

class BurgleBrosFloorPlan
{
	// No strict_types here: cell and wall values arrive from the DB as numeric
	// strings and rely on PHP's coercive mode to become int parameters.
	/** @param array{floor: int|string, vertical: int|string, position: int|string, ...}[] $walls */
	public function __construct(
		private readonly int $size,
		private readonly array $walls
	) {}

	public function rowOf(int $cell): int {
		return intdiv($cell, $this->size);
	}

	public function colOf(int $cell): int {
		return $cell % $this->size;
	}

	// The three geometric facts that move/peek/guard legality is built from.
	/** @return array{same_floor: bool, adjacent: bool, blocked: bool} */
	public function adjacencyDetail(BurgleBrosTilePosition $tile, BurgleBrosTilePosition $other_tile): array {
		$row_delta = abs($this->rowOf($tile->cell) - $this->rowOf($other_tile->cell));
		$col_delta = abs($this->colOf($tile->cell) - $this->colOf($other_tile->cell));
		$orthogonal = ($row_delta == 1 && $col_delta == 0) || ($row_delta == 0 && $col_delta == 1);
		$same_floor = $tile->floor === $other_tile->floor;

		return array(
			'same_floor' => $same_floor,
			'adjacent' => $orthogonal && $same_floor,
			'blocked' => $this->wallBetween($tile->floor, $tile->cell, $other_tile->cell),
		);
	}

	public function wallBetween(int $floor, int $cell, int $other_cell): bool {
		foreach ($this->walls as $wall) {
			if ($wall['floor'] == $floor && $this->wallSeparates($wall, $cell, $other_cell)) {
				return true;
			}
		}
		return false;
	}

	/** @param array{floor: int|string, vertical: int|string, position: int|string, ...} $wall */
	private function wallSeparates(array $wall, int $cell, int $other_cell): bool {
		$row = $this->rowOf($cell);
		$col = $this->colOf($cell);
		$other_row = $this->rowOf($other_cell);
		$other_col = $this->colOf($other_cell);
		$gaps = $this->size - 1;	// wall positions count size-1 gaps per row/column

		if ($wall['vertical'] == 1) {
			$wall_row = intdiv((int) $wall['position'], $gaps);
			$wall_col = (int) ($wall['position'] % $gaps);
			return $row == $other_row && $row == $wall_row
				&& abs($col - $other_col) == 1 && min($col, $other_col) == $wall_col;
		} else {
			$wall_row = (int) ($wall['position'] % $gaps);
			$wall_col = intdiv((int) $wall['position'], $gaps);
			return $col == $other_col && $col == $wall_col
				&& abs($row - $other_row) == 1 && min($row, $other_row) == $wall_row;
		}
	}
}
