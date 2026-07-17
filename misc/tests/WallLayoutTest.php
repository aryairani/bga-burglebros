<?php

use PHPUnit\Framework\TestCase;

/*
 * Validates the default wall layouts declared as ASCII plans in
 * BurgleBrosWallLayouts. Run this suite (see README.md) before deploying any
 * change to the plans.
 */
class WallLayoutTest extends TestCase
{
    /*
     * The hand-maintained position arrays the ASCII plans replaced (formerly
     * BurgleBrosBoard::DEFAULT_WALLS / DEFAULT_WALLS_SIZE_5). Pins the
     * transcription: any edit to a plan fails here until the pin is updated
     * to match.
     */
    private const ORIGINAL_SIZE_4 = array(
        1 => array('vertical' => array(0, 5, 9, 10), 'horizontal' => array(1, 4, 6, 11)),
        2 => array('vertical' => array(0, 1, 2, 9, 10, 11), 'horizontal' => array(4, 7)),
        3 => array('vertical' => array(3, 5, 6, 7, 11), 'horizontal' => array(1, 6, 7)),
    );
    private const ORIGINAL_SIZE_5 = array(
        1 => array('vertical' => array(2, 3, 4, 7, 18, 9, 10), 'horizontal' => array(1, 3, 6, 8, 11, 15, 18, 9, 10), 'shaft' => 12),
        2 => array('vertical' => array(1, 2, 7, 8, 14, 16, 17, 9, 10), 'horizontal' => array(0, 6, 17, 15, 18, 9, 10), 'shaft' => 12),
    );

    /* Sort positions so layouts compare as sets regardless of emission order. */
    private static function normalize($walls) {
        sort($walls['vertical']);
        sort($walls['horizontal']);
        return $walls;
    }

    /*
     * Wall rows for one parsed floor, shaped like getWalls() DB results (all
     * values strings, matching what MySQL returns).
     */
    private static function dbWalls($floor, $walls) {
        $rows = array();
        $id = 1;
        foreach (array('vertical', 'horizontal') as $dir) {
            foreach ($walls[$dir] as $position) {
                $rows[] = array(
                    'id' => (string) $id++,
                    'floor' => (string) $floor,
                    'vertical' => $dir === 'vertical' ? '1' : '0',
                    'position' => (string) $position,
                );
            }
        }
        return $rows;
    }

    public function testPlansParseToOriginalArrays() {
        $pinned = array(
            'bank job' => array(self::ORIGINAL_SIZE_4, BurgleBrosWallLayouts::bankJob()),
            'fort knox' => array(self::ORIGINAL_SIZE_5, BurgleBrosWallLayouts::fortKnox()),
        );
        foreach ($pinned as $name => [$original, $parsed]) {
            $this->assertSame(array_keys($original), array_keys($parsed), "$name floors");
            foreach ($original as $floor => $walls) {
                $this->assertSame(self::normalize($walls), self::normalize($parsed[$floor]), "$name floor $floor");
            }
        }
    }

    /* A known-good minimal plan, checked against hand-computed positions. */
    public function testParseMapsWallsToPositions() {
        $plan = "+---+---+\n"
              . "|   |   |\n"
              . "+   +---+\n"
              . "|       |\n"
              . "+---+---+";
        $this->assertSame(
            array('vertical' => array(0), 'horizontal' => array(1)),
            BurgleBrosWallLayouts::parse($plan)
        );
    }

    public function testParseRejectsMalformedPlans() {
        $cases = array(
            'open border' => "+   +---+\n|   |   |\n+   +---+\n|       |\n+---+---+",
            'stray character' => "+---+---+\n| x |   |\n+   +---+\n|       |\n+---+---+",
            'ragged line' => "+---+---+\n|   |   \n+   +---+\n|       |\n+---+---+",
            'two shafts' => "+---+---+\n|###|###|\n+   +---+\n|       |\n+---+---+",
            'even line count' => "+---+---+\n|   |   |\n+   +---+\n+---+---+",
        );
        foreach ($cases as $name => $plan) {
            try {
                BurgleBrosWallLayouts::parse($plan);
                $this->fail("$name: expected InvalidArgumentException");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /* Every plan set the module exposes: name => [size, floor => walls]. */
    private static function allLayoutSets() {
        return array(
            'bank job' => array(4, BurgleBrosWallLayouts::bankJob()),
            'fort knox' => array(5, BurgleBrosWallLayouts::fortKnox()),
            'office job' => array(4, BurgleBrosWallLayouts::officeJob()),
        );
    }

    // Rules p.13, 2nd ed. Mark III v2.05: 8 walls per 4x4 floor, 12 per 5x5 floor.
    // The Office Job also uses 8 per floor (p.11). The Fort Knox layouts carry 4
    // extra walls per floor: the ones enclosing the empty space (p.12: "The empty
    // space acts as an outer Wall").
    public function testRulebookWallCounts() {
        foreach (self::allLayoutSets() as $name => [$size, $floors]) {
            $expected = $size === 4 ? 8 : 12 + 4;
            foreach ($floors as $floor => $walls) {
                $this->assertCount($expected, array_merge($walls['vertical'], $walls['horizontal']), "$name floor $floor");
            }
        }
    }

    // Every non-shaft cell must be reachable from every other — the same
    // requirement BurgleBrosBoard::checkLayout enforces on random layouts.
    public function testEveryFloorFullyConnected() {
        foreach (self::allLayoutSets() as $name => [$size, $floors]) {
            foreach ($floors as $floor => $walls) {
                $shaft = isset($walls['shaft']) ? $walls['shaft'] : null;
                $plan = new BurgleBrosFloorPlan($size, self::dbWalls($floor, $walls));
                $expected = $size * $size - ($shaft === null ? 0 : 1);
                $this->assertSame($expected, self::reachableCells($plan, $size, $floor, $shaft), "$name floor $floor");
            }
        }
    }

    private static function reachableCells(BurgleBrosFloorPlan $plan, $size, $floor, $shaft) {
        $start = $shaft === 0 ? 1 : 0;
        $seen = array($start => true);
        $stack = array($start);
        while ($stack) {
            $cell = array_pop($stack);
            foreach (self::neighbors($cell, $size) as $next) {
                if ($next === $shaft || isset($seen[$next])) continue;
                if ($plan->wallBetween($floor, $cell, $next)) continue;
                $seen[$next] = true;
                $stack[] = $next;
            }
        }
        return count($seen);
    }

    private static function neighbors($cell, $size) {
        $row = intdiv($cell, $size);
        $col = $cell % $size;
        $neighbors = array();
        if ($col > 0) $neighbors[] = $cell - 1;
        if ($col < $size - 1) $neighbors[] = $cell + 1;
        if ($row > 0) $neighbors[] = $cell - $size;
        if ($row < $size - 1) $neighbors[] = $cell + $size;
        return $neighbors;
    }

    // BurgleBrosBoard::getShaftPosition reads the shaft cell from floor 1 and
    // applies it to every floor, so the plans must agree on it. 4x4 boards
    // have no shaft.
    public function testShaftPlacementConsistent() {
        foreach (BurgleBrosWallLayouts::bankJob() as $floor => $walls) {
            $this->assertArrayNotHasKey('shaft', $walls, "bank job floor $floor");
        }
        foreach (BurgleBrosWallLayouts::officeJob() as $floor => $walls) {
            $this->assertArrayNotHasKey('shaft', $walls, "office job floor $floor");
        }
        $floors = BurgleBrosWallLayouts::fortKnox();
        foreach ($floors as $floor => $walls) {
            $this->assertArrayHasKey('shaft', $walls, "fort knox floor $floor");
            $this->assertSame($floors[1]['shaft'], $walls['shaft'], "fort knox floor $floor");
        }
    }

    // Rules p.12: "The empty space acts as an outer Wall" — the plan must draw
    // walls on all four sides of the shaft cell.
    public function testShaftFullyEnclosed() {
        foreach (BurgleBrosWallLayouts::fortKnox() as $floor => $walls) {
            $plan = new BurgleBrosFloorPlan(5, self::dbWalls($floor, $walls));
            foreach (self::neighbors($walls['shaft'], 5) as $neighbor) {
                $this->assertTrue(
                    $plan->wallBetween($floor, $walls['shaft'], $neighbor),
                    "fort knox floor $floor: shaft {$walls['shaft']} vs $neighbor"
                );
            }
        }
    }
}
