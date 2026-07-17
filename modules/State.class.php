<?php

/*
 * State: ids of the game states declared in states.inc.php
 */
enum State: int
{
    case RandomizeWalls = 5;
    case ChooseCharacter = 7;
    case StartingTile = 8;
    case PlayerTurn = 9;
    case EndTurn = 10;
    case MoveGuard = 11;
    case NextPlayer = 12;
    case CardChoice = 13;
    case TileChoice = 14;
    case PlayerChoice = 15;
    case ProposeTrade = 16;
    case ConfirmTrade = 17;
    case NextTradePlayer = 18;
    case EndTradeOtherPlayer = 19;
    case SpecialChoice = 20;
    case EndAction = 21;
    case DrawToolsAndDiscard = 22;
    case DrawToolsOtherPlayer = 23;
    case TakeCards = 24;
    case SwitchRookMove = 25;
    case ConfirmRookMove = 26;
    case GameOver = 90;
    case GameEnd = 99; // the framework's built-in final state
}
