<?php

/*
 * CardInfo: one card definition in $card_info (material.inc.php) — the face
 * printed on a character/tool/loot/event card. subhead and ability are
 * character-only; choice_description is the prompt shown when playing the
 * card requires picking a target.
 */
final class CardInfo extends CardFace
{
    public function __construct(
        string $name,
        public readonly string $title,
        public readonly string $tooltip,
        public readonly ?string $subhead = null,
        public readonly ?string $ability = null,
        public readonly ?string $choice_description = null,
        int $nbr = 1,
    ) {
        parent::__construct($name, $nbr);
    }
}
