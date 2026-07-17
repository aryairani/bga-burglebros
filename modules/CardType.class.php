<?php

/*
 * CardType: card_type values in the `card` table (keys of $card_types / $card_info in material.inc.php)
 */
enum CardType: int
{
    case Character = 0;
    case Tool = 1;
    case Loot = 2;
    case Event = 3;

    // The per-floor patrol decks (keys of $patrol_types in material.inc.php)
    case Patrol1 = 4;
    case Patrol2 = 5;
    case Patrol3 = 6;

    static function patrol(int $floor): self {
        return self::from($floor + 3);
    }
}
