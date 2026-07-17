<?php

/*
 * Copy of the pre-BurgleBrosPathfinder guard pathfinding from
 * burglebros.game.php (working tree of 2026-07-17, on commit 9263507):
 * findShortestPathClockwise, neighbors, breakTie, clockwise and directions.
 * Used as the reference implementation in differential tests, so the logic
 * below must never be edited. Mechanical adaptations only:
 *   - operates on cells instead of DB tile rows and returns cells, not tile
 *     ids ($tiles[$idx] lookups and the ['location_arg']/['id'] plumbing
 *     dropped);
 *   - isTileAdjacent(..., 'guard') is inlined as orthogonal-and-not-walled
 *     against a BurgleBrosFloorPlan;
 *   - the clockwise mappings arrive via the constructor instead of
 *     $this->clockwise_mappings;
 *   - `$mappings[$dirs]` is `?? ''` — same chosen path as the original's
 *     undefined-key fallthrough (null[0] == $ldir is false, so $right wins),
 *     without the PHP 8 warnings.
 */
class LegacyPathfinder
{
    private $plan;
    private $floor;
    private $mappings;

    public function __construct(BurgleBrosFloorPlan $plan, int $floor, array $mappings) {
        $this->plan = $plan;
        $this->floor = $floor;
        $this->mappings = $mappings;
    }

    private function isTileAdjacent($cell, $other_cell) {
        $row_delta = abs($this->plan->rowOf($cell) - $this->plan->rowOf($other_cell));
        $col_delta = abs($this->plan->colOf($cell) - $this->plan->colOf($other_cell));
        $orthogonal = ($row_delta == 1 && $col_delta == 0) || ($row_delta == 0 && $col_delta == 1);
        return $orthogonal && !$this->plan->wallBetween($this->floor, $cell, $other_cell);
    }

    function directions($left, $right) {
        $grid = $this->plan;
        $dx = $grid->colOf($left) - $grid->colOf($right);
        $dy = $grid->rowOf($right) - $grid->rowOf($left);

        // Keep alphabetical
        $dirs = "";
        if ($dy > 0) {
            $dirs .= 'D';
        }
        if ($dx > 0) {
            $dirs .= 'L';
        }
        if ($dx < 0) {
            $dirs .= 'R';
        }
        if ($dy < 0) {
            $dirs .= 'U';
        }
        return $dirs;
    }

    function clockwise($current, $end, $left, $right) {
        $orientation = $this->directions($current, $end);
        $ldir = $this->directions($current, $left);
        $rdir = $this->directions($current, $right);
        $dirs = $ldir < $rdir ? $ldir.$rdir : $rdir.$ldir;
        $mappings = $this->mappings[$orientation];
        $result = $mappings[$dirs] ?? '';
        if ($result !== '' && $result[0] == $ldir) {
            return $left;
        } else {
            return $right;
        }
    }

    function neighbors($cells, $current_cell, $except=array()) {
        return array_filter($cells, function($cell) use ($current_cell, $except) {
            return !in_array($cell, $except) && $this->isTileAdjacent($cell, $current_cell);
        });
    }

    function breakTie($end, $paths) {
        if (count($paths) == 1) {
            return $paths[0];
        }

        sort($paths);

        if (count($paths[0]) != count($paths[1])) {
            return $paths[0];
        } else {
            $path1 = $paths[0];
            $path2 = $paths[1];
            $idx = 0;
            while($path1 != null && $path2 != null && $path1[$idx] == $path2[$idx]) $idx++;
            // Must check $end because $end can be far far away and create a bias for the clockwise analysis
            // Check $end on the next common tile of $paths
            $temp_end = $end;
            for ($i=$idx; $i <= count($path1); $i++) {
                if ($path1[$i] == $path2[$i]) {
                    $temp_end = $path1[$i];
                    break;
                }
            }
            $most_cw = $this->clockwise($path1[$idx-1], $temp_end, $path1[$idx], $path2[$idx]);
            return $most_cw == $path1[$idx] ? $path1 : $path2;
        }
    }

    function findShortestPathClockwise($start, $end) {
        $cells = array();
        $size = $this->plan->size;
        for ($i = 0; $i < $size * $size; $i++) {
            $cells[$i] = $i;
        }

        $path = array($start);
        $avail = array($start=>$this->neighbors($cells, $start));
        $paths = array();
        while (count($path) > 0) {
            $current = $path[count($path) - 1];
            $opts = $avail[$current];
            if ($current == $end) {
                $paths [] = $path;
                array_pop($path);
                if (count($path) > 0) {
                    $last_avail = &$avail[$path[count($path) - 1]];
                    unset($last_avail[$current]);
                }
            } else if(count($opts) == 0) {
                array_pop($path);
                if (count($path) > 0) {
                    $last_avail = &$avail[$path[count($path) - 1]];
                    unset($last_avail[$current]);
                }
            } else {
                $next = array_keys($opts)[0];
                if (!isset($avail[$next]) || count($avail[$next]) == 0) {
                    $avail[$next] = $this->neighbors($cells, $next, $path);
                }
                $path [] = $next;
            }
        }

        return $this->breakTie($end, $paths);
    }
}
