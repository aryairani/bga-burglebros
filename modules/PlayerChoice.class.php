<?php

/*
 * PlayerChoice: values of the 'playerChoice' game state value — why the playerChoice
 * state was entered (keys of $player_choices in material.inc.php)
 */
final class PlayerChoice
{
    const NONE = 0;
    const TRADE = 1;
    const ROOK1 = 2;
    const ROOK2 = 3;
    const SQUEAK = 4;
}
