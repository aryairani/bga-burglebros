<?php

/*
 * State: ids of the game states declared in states.inc.php
 */
final class State
{
    const RANDOMIZE_WALLS = 5;
    const CHOOSE_CHARACTER = 7;
    const STARTING_TILE = 8;
    const PLAYER_TURN = 9;
    const END_TURN = 10;
    const MOVE_GUARD = 11;
    const NEXT_PLAYER = 12;
    const CARD_CHOICE = 13;
    const TILE_CHOICE = 14;
    const PLAYER_CHOICE = 15;
    const PROPOSE_TRADE = 16;
    const CONFIRM_TRADE = 17;
    const NEXT_TRADE_PLAYER = 18;
    const END_TRADE_OTHER_PLAYER = 19;
    const SPECIAL_CHOICE = 20;
    const END_ACTION = 21;
    const DRAW_TOOLS_AND_DISCARD = 22;
    const DRAW_TOOLS_OTHER_PLAYER = 23;
    const TAKE_CARDS = 24;
    const SWITCH_ROOK_MOVE = 25;
    const CONFIRM_ROOK_MOVE = 26;
    const GAME_OVER = 90;
    const GAME_END = 99; // the framework's built-in final state
}
