<?php

require_once __DIR__ . '/BurgleBrosGrid.class.php';

/*
 * BurgleBrosFloorPlan: the physical layout of the building — the grid
 * (BurgleBrosGrid, which this extends; size/cell/wall-position vocabulary is
 * documented there) plus where the walls are. Pure geometry, no database or
 * framework access, so it is unit-testable locally (see misc/tests/).
 * Construct it from getSquareSize() and getWalls(); everything else is a query
 * against it.
 *
 * Vocabulary:
 *   tile      A room card as stored in the `tile` DB table: 'location' is the
 *             string "floorN" (char 5 is the floor number), 'location_arg' is
 *             the cell it occupies. Positions enter this class as
 *             BurgleBrosTilePosition values (fromRow() converts a DB row).
 *   wall      One wall piece as stored in the `wall` DB table: {floor, vertical,
 *             position}.
 *   adjacent  Two tiles on the same floor whose cells share
 *             an edge. Diagonals are never adjacent — Rules p.5, 2nd ed. Mark III
 *             v2.05: "You may not Move diagonally or through Walls."
 *   blocked   A wall stands between the two cells (the "through Walls" half of
 *             the same rule). Movement and peeking need adjacent && !blocked;
 *             special tiles and abilities relax this elsewhere.
 */

class BurgleBrosFloorPlan extends BurgleBrosGrid
{
	/** @param array{floor: int|string, vertical: int|string, position: int|string, ...}[] $walls */
	public function __construct(
		int $size,
		private readonly array $walls
	) {
		parent::__construct($size);
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
		$walls_on_floor = array_filter($this->walls, fn($wall) => $wall['floor'] == $floor);
		foreach ($walls_on_floor as $wall) {
			if ($this->wallSeparates($wall, $cell, $other_cell)) {
				return true;
			}
		}
		return false;
	}

	/** @param array{floor: int|string, vertical: int|string, position: int|string, ...} $wall */
	private function wallSeparates(array $wall, int $cell, int $other_cell): bool {
		[$a, $b] = $this->cellsSeparatedBy($wall);
		return ($cell == $a && $other_cell == $b) || ($cell == $b && $other_cell == $a);
	}
}
