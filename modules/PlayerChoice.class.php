<?php

/*
 * PlayerChoice: values of the 'playerChoice' game state value — why the playerChoice
 * state was entered (keys of $player_choices in material.inc.php)
 */
enum PlayerChoice: int
{
    case None = 0;
    case Trade = 1;
    case Rook1 = 2;
    case Rook2 = 3;
    case Squeak = 4;
}
