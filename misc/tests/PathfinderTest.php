<?php

use PHPUnit\Framework\TestCase;

/*
 * BurgleBrosPathfinder: rulebook examples, then a differential test against
 * LegacyPathfinder (the enumerate-all-paths implementation it replaced) over
 * the shipped layouts and randomly generated legal ones.
 */
class PathfinderTest extends TestCase
{
    /** Wall rows shaped like getWalls() DB results (all values strings). */
    private static function dbWalls($floor, array $vertical, array $horizontal) {
        $rows = array();
        $id = 1;
        foreach (array(1 => $vertical, 0 => $horizontal) as $isVertical => $positions) {
            foreach ($positions as $position) {
                $rows[] = array(
                    'id' => (string) $id++,
                    'floor' => (string) $floor,
                    'vertical' => (string) $isVertical,
                    'position' => (string) $position,
                );
            }
        }
        return $rows;
    }

    /*
     * Fixture floors are drawn in the BurgleBrosWallLayouts plan format.
     * Cells are numbered reading order:  0  1  2  3
     *                                    4  5  6  7
     *                                    8  9 10 11
     *                                   12 13 14 15
     */
    private static function finderFromPlan($plan, $floor = 1) {
        $size = intdiv(count(explode("\n", $plan)), 2);
        $walls = BurgleBrosWallLayouts::parse($plan);
        return new BurgleBrosPathfinder(
            new BurgleBrosFloorPlan($size, self::dbWalls($floor, $walls['vertical'], $walls['horizontal'])),
            $floor
        );
    }

    private const OPEN_FLOOR = <<<'PLAN'
        +---+---+---+---+
        |               |
        +   +   +   +   +
        |               |
        +   +   +   +   +
        |               |
        +   +   +   +   +
        |               |
        +---+---+---+---+
        PLAN;

    public function testTrivialAndStraightPaths() {
        $finder = self::finderFromPlan(self::OPEN_FLOOR);
        $this->assertSame(array(5), $finder->shortestPathClockwise(5, 5));
        $this->assertSame(array(0, 1, 2, 3), $finder->shortestPathClockwise(0, 3));
        $this->assertSame(array(12, 8, 4, 0), $finder->shortestPathClockwise(12, 0));
    }

    /*
     * A wall-free diagonal step offers two tied paths; "most clockwise" means
     * the guard leads with R when headed down-right, D when down-left, L when
     * up-left, U when up-right (Rules p.8-9).
     */
    public function testDiamondForkTakesClockwiseBranch() {
        $finder = self::finderFromPlan(self::OPEN_FLOOR);
        $this->assertSame(array(0, 1, 5), $finder->shortestPathClockwise(0, 5), 'down-right leads R');
        $this->assertSame(array(1, 5, 4), $finder->shortestPathClockwise(1, 4), 'down-left leads D');
        $this->assertSame(array(5, 4, 0), $finder->shortestPathClockwise(5, 0), 'up-left leads L');
        $this->assertSame(array(4, 0, 1), $finder->shortestPathClockwise(4, 1), 'up-right leads U');
    }

    // The walled-off hallway forces 0 -> 2 into two tied 4-step detours
    // through the second row (via 1 or via 4). They reconverge at 5, down-right
    // of the fork, so the most clockwise route leads R: 0, 1, 5, 6, 2.
    public function testWallForcesDetour() {
        $finder = self::finderFromPlan(<<<'PLAN'
            +---+---+---+---+
            |       |       |
            +   +   +   +   +
            |               |
            +   +   +   +   +
            |               |
            +   +   +   +   +
            |               |
            +---+---+---+---+
            PLAN);
        $this->assertSame(array(0, 1, 5, 6, 2), $finder->shortestPathClockwise(0, 2));
    }

    public function testUnreachableCellThrows() {
        $finder = self::finderFromPlan(<<<'PLAN'
            +---+---+---+---+
            |   |           |
            +---+   +   +   +
            |               |
            +   +   +   +   +
            |               |
            +   +   +   +   +
            |               |
            +---+---+---+---+
            PLAN);
        $this->expectException(RuntimeException::class);
        $finder->shortestPathClockwise(0, 15);
    }

    public function testMatchesLegacyOnShippedLayouts() {
        foreach (BurgleBrosWallLayouts::bankJob() as $floor => $walls) {
            $this->compareAllPairs(4, self::dbWalls($floor, $walls['vertical'], $walls['horizontal']), $floor, "bank job floor $floor");
        }
        foreach (BurgleBrosWallLayouts::officeJob() as $floor => $walls) {
            $this->compareAllPairs(4, self::dbWalls($floor, $walls['vertical'], $walls['horizontal']), $floor, "office job floor $floor");
        }
        foreach (BurgleBrosWallLayouts::fortKnox() as $floor => $walls) {
            $this->compareAllPairs(5, self::dbWalls($floor, $walls['vertical'], $walls['horizontal']), $floor, "fort knox floor $floor", $walls['shaft']);
        }
    }

    public function testMatchesLegacyOnRandomLayouts() {
        mt_srand(20260717);
        for ($layout = 0; $layout < 30; $layout++) {
            [$vertical, $horizontal] = self::randomLegalWalls(4, 8);
            $label = 'layout ' . $layout . ' v=[' . implode(',', $vertical) . '] h=[' . implode(',', $horizontal) . ']';
            $this->compareAllPairs(4, self::dbWalls(1, $vertical, $horizontal), 1, $label);
        }
    }

    private function compareAllPairs($size, array $walls, $floor, $label, $shaft = null) {
        $plan = new BurgleBrosFloorPlan($size, $walls);
        $new = new BurgleBrosPathfinder($plan, $floor);
        $legacy = new LegacyPathfinder($plan, $floor, BurgleBrosPathfinder::CLOCKWISE_MAPPINGS);
        for ($start = 0; $start < $size * $size; $start++) {
            for ($end = 0; $end < $size * $size; $end++) {
                if ($start === $shaft || $end === $shaft) continue;
                $this->assertSame(
                    $legacy->findShortestPathClockwise($start, $end),
                    $new->shortestPathClockwise($start, $end),
                    "$label: $start -> $end"
                );
            }
        }
    }

    /*
     * Random legal layout, mirroring BurgleBrosBoard::generateWalls /
     * checkLayout: n distinct walls, every cell still reachable. Slots
     * 0..(size*(size-1)-1) are vertical positions, the rest horizontal.
     */
    private static function randomLegalWalls($size, $n) {
        $slots_per_direction = $size * ($size - 1);
        while (true) {
            $slots = array();
            while (count($slots) < $n) {
                $slots[mt_rand(0, 2 * $slots_per_direction - 1)] = true;
            }
            $vertical = array();
            $horizontal = array();
            foreach (array_keys($slots) as $slot) {
                if ($slot < $slots_per_direction) {
                    $vertical[] = $slot;
                } else {
                    $horizontal[] = $slot - $slots_per_direction;
                }
            }
            if (self::fullyConnected(new BurgleBrosFloorPlan($size, self::dbWalls(1, $vertical, $horizontal)), $size)) {
                return array($vertical, $horizontal);
            }
        }
    }

    private static function fullyConnected(BurgleBrosFloorPlan $plan, $size) {
        $seen = array(0 => true);
        $stack = array(0);
        while ($stack) {
            $cell = array_pop($stack);
            $row = intdiv($cell, $size);
            $col = $cell % $size;
            $neighbors = array();
            if ($col > 0) $neighbors[] = $cell - 1;
            if ($col < $size - 1) $neighbors[] = $cell + 1;
            if ($row > 0) $neighbors[] = $cell - $size;
            if ($row < $size - 1) $neighbors[] = $cell + $size;
            foreach ($neighbors as $next) {
                if (!isset($seen[$next]) && !$plan->wallBetween(1, $cell, $next)) {
                    $seen[$next] = true;
                    $stack[] = $next;
                }
            }
        }
        return count($seen) === $size * $size;
    }
}
