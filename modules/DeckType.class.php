<?php

/*
 * DeckType: descriptor for a card deck (values of $card_types / $patrol_types
 * in material.inc.php). name is an identifier — it builds card-table location
 * names and the client uses it for CSS classes and image filenames.
 */
final class DeckType
{
    public function __construct(public readonly string $name) {}

    function deckName(): string {
        return $this->name . '_deck';
    }

    function discardName(): string {
        return $this->name . '_discard';
    }
}
