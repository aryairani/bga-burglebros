<?php

use PHPUnit\Framework\TestCase;

class GridTest extends TestCase
{
    private static function wall($vertical, $position) {
        // String values, matching what MySQL returns.
        return array('vertical' => (string) $vertical, 'position' => (string) $position);
    }

    public function testRowColOf() {
        $grid = new BurgleBrosGrid(4);
        $this->assertSame(0, $grid->rowOf(3));
        $this->assertSame(3, $grid->colOf(3));
        $this->assertSame(1, $grid->rowOf(6));
        $this->assertSame(2, $grid->colOf(6));
        $this->assertSame(3, $grid->rowOf(15));
        $this->assertSame(3, $grid->colOf(15));

        $grid5 = new BurgleBrosGrid(5);
        $this->assertSame(2, $grid5->rowOf(12));
        $this->assertSame(2, $grid5->colOf(12));
    }

    public function testManhattanDistance() {
        $grid = new BurgleBrosGrid(4);
        $this->assertSame(0, $grid->manhattanDistance(5, 5));
        $this->assertSame(1, $grid->manhattanDistance(5, 6));
        $this->assertSame(1, $grid->manhattanDistance(5, 1));
        $this->assertSame(2, $grid->manhattanDistance(5, 2));   // diagonal step
        $this->assertSame(6, $grid->manhattanDistance(0, 15));  // opposite corners
        $this->assertSame(3, $grid->manhattanDistance(3, 0));   // not |3-0| % wrap: same row
        // Cells 3 and 4 are on different rows despite consecutive numbering.
        $this->assertSame(4, $grid->manhattanDistance(3, 4));
    }

    public function testCellsSeparatedBy() {
        $grid = new BurgleBrosGrid(4);
        // Vertical wall p: row = p / 3, col = p % 3; between (row, col) and (row, col+1).
        $this->assertSame(array(0, 1), $grid->cellsSeparatedBy(self::wall(1, 0)));
        $this->assertSame(array(4, 5), $grid->cellsSeparatedBy(self::wall(1, 3)));
        $this->assertSame(array(14, 15), $grid->cellsSeparatedBy(self::wall(1, 11)));
        // Horizontal wall p: row = p % 3, col = p / 3; between (row, col) and (row+1, col).
        $this->assertSame(array(0, 4), $grid->cellsSeparatedBy(self::wall(0, 0)));
        $this->assertSame(array(9, 13), $grid->cellsSeparatedBy(self::wall(0, 5)));
        $this->assertSame(array(11, 15), $grid->cellsSeparatedBy(self::wall(0, 11)));

        $grid5 = new BurgleBrosGrid(5);
        $this->assertSame(array(6, 7), $grid5->cellsSeparatedBy(self::wall(1, 5)));
        $this->assertSame(array(17, 22), $grid5->cellsSeparatedBy(self::wall(0, 11)));
        $this->assertSame(array(7, 12), $grid5->cellsSeparatedBy(self::wall(0, 9)));
    }

    /*
     * Ties cellsSeparatedBy to FloorPlan blocking: a floor plan whose only wall
     * is w must report exactly the pair cellsSeparatedBy(w) as blocked, over
     * every adjacent cell pair. (wallBetween's own correctness is pinned
     * separately by FloorPlanTest's differential test against LegacyGeometry.)
     */
    public function testCellsSeparatedByMatchesWallBetween() {
        foreach (array(4, 5) as $size) {
            $grid = new BurgleBrosGrid($size);
            $max_position = $size * ($size - 1) - 1;
            foreach (array(1, 0) as $vertical) {
                for ($position = 0; $position <= $max_position; $position++) {
                    $wall = self::wall($vertical, $position);
                    $plan = new BurgleBrosFloorPlan($size, array(
                        array('id' => '1', 'floor' => '1', 'vertical' => (string) $vertical, 'position' => (string) $position),
                    ));
                    $separated = $grid->cellsSeparatedBy($wall);
                    foreach (self::adjacentPairs($size) as [$a, $b]) {
                        $this->assertSame(
                            $separated == array($a, $b),
                            $plan->wallBetween(1, $a, $b),
                            "size $size vertical $vertical position $position: cells $a-$b"
                        );
                        $this->assertSame(
                            $plan->wallBetween(1, $a, $b),
                            $plan->wallBetween(1, $b, $a),
                            "size $size vertical $vertical position $position: cells $b-$a"
                        );
                    }
                }
            }
        }
    }

    /** @return array{int, int}[] every orthogonally adjacent cell pair, lowest first */
    private static function adjacentPairs($size) {
        $pairs = array();
        for ($cell = 0; $cell < $size * $size; $cell++) {
            if ($cell % $size < $size - 1) {
                $pairs[] = array($cell, $cell + 1);
            }
            if ($cell + $size < $size * $size) {
                $pairs[] = array($cell, $cell + $size);
            }
        }
        return $pairs;
    }
}
