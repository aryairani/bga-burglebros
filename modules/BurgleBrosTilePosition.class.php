<?php

/*
 * BurgleBrosTilePosition: where a tile sits in the building — its floor and
 * cell as typed values instead of a mixed-type array. Build one from a `tile`
 * DB row with fromRow(), which parses the row's "floorN" location string and
 * rejects rows that are not on a floor. Pure data, no DB or framework access;
 * nothing here changes what the DB stores.
 */

final class BurgleBrosTilePosition
{
	public function __construct(
		// floor number, e.g., 1 for "floor1"
		public readonly int $floor,
		// cell number within the floor, e.g. 0..15 or 0..24
		public readonly int $cell
	) {}

	/** @param array{location: string, location_arg: int|string, ...} $row */
	public static function fromRow(array $row): self {
		if (!str_starts_with($row['location'], 'floor')) {
			throw new InvalidArgumentException("tile is not on a floor: '{$row['location']}'");
		}
		return new self((int) substr($row['location'], 5), (int) $row['location_arg']);
	}
}
