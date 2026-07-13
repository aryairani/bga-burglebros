<?php

use PHPUnit\Framework\TestCase;

class TilePositionTest extends TestCase
{
    public function testFromRowParsesFloorAndCell() {
        $pos = BurgleBrosTilePosition::fromRow(array('location' => 'floor2', 'location_arg' => '14'));
        $this->assertSame(2, $pos->floor);
        $this->assertSame(14, $pos->cell);
    }

    public function testFromRowRejectsNonFloorLocation() {
        $this->expectException(InvalidArgumentException::class);
        BurgleBrosTilePosition::fromRow(array('location' => 'deck', 'location_arg' => '0'));
    }
}
