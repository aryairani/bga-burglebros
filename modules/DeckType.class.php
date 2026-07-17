<?php

/*
 * DeckType: the card decks (values of $card_types / $patrol_types in
 * material.inc.php). The backing value is an identifier — it builds
 * card-table location names and the client uses it for CSS classes
 * and image filenames.
 */
enum DeckType: string
{
    case Characters = 'characters';
    case Tools = 'tools';
    case Loot = 'loot';
    case Events = 'events';
    case Patrol1 = 'patrol1';
    case Patrol2 = 'patrol2';
    case Patrol3 = 'patrol3';

    static function patrol(int $floor): self {
        return self::from("patrol$floor");
    }

    function cardType(): CardType {
        return match ($this) {
            self::Characters => CardType::Character,
            self::Tools => CardType::Tool,
            self::Loot => CardType::Loot,
            self::Events => CardType::Event,
            self::Patrol1 => CardType::Patrol1,
            self::Patrol2 => CardType::Patrol2,
            self::Patrol3 => CardType::Patrol3,
        };
    }

    function deckName(): string {
        return $this->value . '_deck';
    }

    function discardName(): string {
        return $this->value . '_discard';
    }

    function oopName(): string {
        return $this->value . '_oop';
    }
}
