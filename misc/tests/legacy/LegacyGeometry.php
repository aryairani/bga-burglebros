<?php

/*
 * Verbatim copy of tileAdjacencyDetail from burglebros.game.php (commit 05e3492,
 * lines 968-1006), with $this->getSquareSize()/$this->getWalls() served by the
 * constructor. Used as the reference implementation in differential tests: the
 * body below must never be edited, so that BurgleBrosGeometry is always compared
 * against the original behavior.
 */

class LegacyGeometry
{
    private $size;
    private $walls;

    public function __construct($size, $walls) {
        $this->size = $size;
        $this->walls = $walls;
    }

    function getSquareSize() {
        return $this->size;
    }

    function getWalls() {
        return $this->walls;
    }

    function tileAdjacencyDetail($tile, $other_tile, $walls=null) {
        if (!isset($walls)) {
            $walls = $this->getWalls();
        }

        $size_sq = $this->getSquareSize();
        $tindex = $tile['location_arg'];
        $trow = floor($tindex / $size_sq);
        $tcol = $tindex % $size_sq;

        $pindex = $other_tile['location_arg'];
        $prow = floor($pindex / $size_sq);
        $pcol = $pindex % $size_sq;

        $same_floor = $tile['location'] == $other_tile['location'];
        // Check row or column adjency and same floor
        $adjacent = (($trow == $prow && abs($tcol - $pcol) == 1) || ($tcol == $pcol && abs($trow - $prow) == 1)) &&
            $tile['location'][5] == $other_tile['location'][5];
        $blocked = false;
        foreach ($walls as $wall) {
            if($wall['floor'] == $tile['location'][5]) {
                $size = $this->getSquareSize();
                $dec = $size - 1;
                $wrow = $wall['vertical'] == 1 ? floor($wall['position'] / $dec) : $wall['position'] % $dec;
                $wcol = $wall['vertical'] == 0 ? floor($wall['position'] / $dec) : $wall['position'] % $dec;
                $vertical = ($trow == $prow && $trow == $wrow && abs($tcol - $pcol) == 1) && min($tcol, $pcol) == $wcol;
                $horizontal = ($tcol == $pcol && $tcol == $wcol && abs($trow - $prow) == 1) && min($trow, $prow) == $wrow;
                if (($wall['vertical'] == 1 && $vertical) || ($wall['vertical'] == 0 && $horizontal)) {
                    $blocked = true;
                    break;
                }
            }
        }
        return array(
            'same_floor' => $same_floor,
            'adjacent' => $adjacent,
            'blocked' => $blocked
        );
    }
}
