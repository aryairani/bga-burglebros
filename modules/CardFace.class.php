<?php

/*
 * CardFace: one entry in a material card list ($card_info in BurgleBrosMaterial,
 * patrolInfo() in burglebros.game.php). A face's 1-based position in its type's
 * list is the card's type_arg in the `card` table. Patrol faces are bare
 * CardFaces whose name is the grid coordinate printed on the card.
 */
class CardFace implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly int $nbr = 1,
    ) {}

    /** The client expects unset fields to be absent from gamedatas, not null. */
    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }
}
