<?php

use PHPUnit\Framework\TestCase;

class FloorPlanTest extends TestCase
{
    /*
     * Convert a layout array (floor => direction => positions, as returned by the
     * BurgleBrosWallLayouts accessors) into wall rows shaped like getWalls()
     * results: all values are strings, matching what MySQL returns.
     */
    private static function dbWalls($layouts) {
        $rows = array();
        $id = 1;
        foreach ($layouts as $floor => $directions) {
            foreach ($directions as $dir => $positions) {
                if ($dir === 'shaft') continue;
                foreach ($positions as $position) {
                    $rows[] = array(
                        'id' => (string) $id++,
                        'floor' => (string) $floor,
                        'vertical' => $dir === 'vertical' ? '1' : '0',
                        'position' => (string) $position,
                    );
                }
            }
        }
        return $rows;
    }

    private static function tile($floor, $cell) {
        return array('location' => "floor$floor", 'location_arg' => (string) $cell);
    }

    private static function pos($floor, $cell) {
        return BurgleBrosTilePosition::fromRow(self::tile($floor, $cell));
    }

    private static function randomWalls($size, $seed, $count = 8) {
        mt_srand($seed);
        $rows = array();
        $max_position = $size * ($size - 1) - 1;
        for ($i = 0; $i < $count; $i++) {
            $rows[] = array(
                'id' => (string) ($i + 1),
                'floor' => (string) mt_rand(1, 2),
                'vertical' => (string) mt_rand(0, 1),
                'position' => (string) mt_rand(0, $max_position),
            );
        }
        return $rows;
    }

    public function testMatchesLegacyForAllTilePairs() {
        $wall_sets = array(
            'size4 no walls' => array(4, array()),
            'size4 default (Bank Job)' => array(4, self::dbWalls(BurgleBrosWallLayouts::bankJob())),
            'size4 random seed 1' => array(4, self::randomWalls(4, 1)),
            'size4 random seed 2' => array(4, self::randomWalls(4, 2)),
            'size4 random dense' => array(4, self::randomWalls(4, 3, 24)),
            'size5 no walls' => array(5, array()),
            'size5 default (Fort Knox)' => array(5, self::dbWalls(BurgleBrosWallLayouts::fortKnox())),
            'size5 random seed 4' => array(5, self::randomWalls(5, 4, 12)),
        );

        foreach ($wall_sets as $name => [$size, $walls]) {
            $legacy = new LegacyGeometry($size, $walls);
            $plan = new BurgleBrosFloorPlan($size, $walls);
            $cell_count = $size * $size;
            foreach (array(1, 2) as $floor_a) {
                foreach (array(1, 2) as $floor_b) {
                    for ($a = 0; $a < $cell_count; $a++) {
                        for ($b = 0; $b < $cell_count; $b++) {
                            $ta = self::tile($floor_a, $a);
                            $tb = self::tile($floor_b, $b);
                            $this->assertSame(
                                $legacy->tileAdjacencyDetail($ta, $tb),
                                $plan->adjacencyDetail(
                                    BurgleBrosTilePosition::fromRow($ta),
                                    BurgleBrosTilePosition::fromRow($tb)
                                ),
                                "$name: floor$floor_a:$a vs floor$floor_b:$b"
                            );
                        }
                    }
                }
            }
        }
    }

    // Rules p.5: "You may not Move diagonally or through Walls."
    public function testWallBlocksAdjacentTiles() {
        $plan = new BurgleBrosFloorPlan(4, self::dbWalls(BurgleBrosWallLayouts::bankJob()));
        // Bank Job floor 1 has a vertical wall at position 0: between cells 0 and 1.
        $detail = $plan->adjacencyDetail(self::pos(1, 0), self::pos(1, 1));
        $this->assertTrue($detail['adjacent']);
        $this->assertTrue($detail['blocked']);
        // Cells 1 and 2 have no wall between them.
        $detail = $plan->adjacencyDetail(self::pos(1, 1), self::pos(1, 2));
        $this->assertTrue($detail['adjacent']);
        $this->assertFalse($detail['blocked']);
    }

    // The Acrobat's Climb Window requires an outer tile; isInteriorCell names the cells it rejects.
    public function testInnerCells() {
        $plan4 = new BurgleBrosFloorPlan(4, array());
        $inner4 = array_values(array_filter(range(0, 15), fn($c) => $plan4->isInteriorCell($c)));
        $this->assertSame(array(5, 6, 9, 10), $inner4);

        $plan5 = new BurgleBrosFloorPlan(5, array());
        $inner5 = array_values(array_filter(range(0, 24), fn($c) => $plan5->isInteriorCell($c)));
        $this->assertSame(array(6, 7, 8, 11, 12, 13, 16, 17, 18), $inner5);
    }

    public function testDiagonalIsNotAdjacent() {
        $plan = new BurgleBrosFloorPlan(4, array());
        $detail = $plan->adjacencyDetail(self::pos(1, 0), self::pos(1, 5));
        $this->assertFalse($detail['adjacent']);
    }

    public function testOtherFloorIsNotAdjacent() {
        $plan = new BurgleBrosFloorPlan(4, array());
        $detail = $plan->adjacencyDetail(self::pos(1, 2), self::pos(2, 3));
        $this->assertFalse($detail['adjacent']);
        $this->assertFalse($detail['same_floor']);
    }

    // Rules p.12: the Fort Knox empty space (cell 12 in the default layout) acts
    // as an outer Wall — all four neighbors must be blocked, on both floors.
    public function testFortKnoxEmptySpaceIsWalledOff() {
        $plan = new BurgleBrosFloorPlan(5, self::dbWalls(BurgleBrosWallLayouts::fortKnox()));
        foreach (array(1, 2) as $floor) {
            foreach (array(7, 11, 13, 17) as $neighbor) {
                $detail = $plan->adjacencyDetail(self::pos($floor, 12), self::pos($floor, $neighbor));
                $this->assertTrue($detail['blocked'], "floor $floor: cell 12 vs $neighbor");
            }
        }
    }
}
