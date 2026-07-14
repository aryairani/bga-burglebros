<?php

/*
 * CardType: card_type values in the `card` table (keys of $card_types / $card_info in material.inc.php)
 */
final class CardType
{
    const CHARACTER = 0;
    const TOOL = 1;
    const LOOT = 2;
    const EVENT = 3;

    // The per-floor patrol decks are card types 4/5/6 (keys of $patrol_types in material.inc.php)
    static function patrol(int $floor): int {
        return $floor + 3;
    }
}
