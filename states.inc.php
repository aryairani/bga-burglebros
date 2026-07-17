<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * burglebros implementation : © Brian Gregg baritonehands@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * burglebros game states description
 *
 */

require_once(__DIR__ . '/modules/State.class.php');

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with 'game' type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by 'st' (ex: 'stMyGameStateName').
   _ possibleactions: array that specify possible player actions on this step. It allows you to use 'checkAction'
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in 'nextState' PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on 'onEnteringState' or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!


$machinestates = array(
    State::RandomizeWalls->value => array(
        'name' => 'randomizeWalls',
        'description' => clienttranslate('The administrator of the table can generate new walls for this game'),
        'descriptionmyturn' => clienttranslate('${you} can generate a new set of walls for this game'),
        'type' => 'activeplayer',
        'action' => 'stRandomizeWalls',
        'possibleactions' => array( 'randomizeWalls' ),
        'transitions' => array( 'startGame' => State::ChooseCharacter->value )
    ),

    State::ChooseCharacter->value => array(
        'name' => 'chooseCharacter',
        'description' => clienttranslate('Other players must choose a character'),
        'descriptionmyturn' => clienttranslate('${you} must choose a character'),
        'type' => 'multipleactiveplayer',
        'action' => 'stChooseCharacter',
        'args' => 'argChooseCharacter',
        'possibleactions' => array( 'playCard' ),
        'transitions' => array( 'chooseCharacter' => State::StartingTile->value, 'nextPlayer' => State::ChooseCharacter->value )
    ),

    State::StartingTile->value => array(
        'name' => 'startingTile',
        'description' => clienttranslate('${actplayer} must choose a starting tile'),
        'descriptionmyturn' => clienttranslate('${you} must choose a starting tile'),
        'type' => 'activeplayer',
        'args' => 'argStartingTile',
        'possibleactions' => array( 'chooseStartingTile' ),
        'transitions' => array( '' => State::PlayerTurn->value )
    ),

    State::PlayerTurn->value => array(
        'name' => 'playerTurn',
        'description' => clienttranslate('${actplayer} have ${actions_remaining} actions remaining'),
        'descriptionmyturn' => clienttranslate('${you} have ${actions_remaining} actions remaining'),
        'type' => 'activeplayer',
        // 'action' => 'stPlayerTurn',
        'args' => 'argPlayerTurn',
        'updateGameProgression' => true,
        'possibleactions' => array( 'hack', 'move', 'peek', 'addSafeDie', 'rollSafeDice', 'playCard', 'characterAction', 'trade', 'pickUpCat', 'takeCards', 'pass', 'escape', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'endTurn' => State::EndTurn->value, 'nextPlayer' => State::NextPlayer->value, 'cardChoice' => State::CardChoice->value, 'tileChoice' => State::TileChoice->value, 'playerChoice' => State::PlayerChoice->value, 'proposeTrade' => State::ProposeTrade->value, 'takeCards' => State::TakeCards->value, 'specialChoice' => State::SpecialChoice->value, 'rookChoice' => State::SwitchRookMove->value, 'chooseAlarm' => State::SpecialChoice->value, 'restartTurn' => State::PlayerTurn->value, 'gameOver' => State::GameOver->value )
    ),

    State::EndTurn->value => array(
        'name' => 'endTurn',
        'description' => clienttranslate('Triggering end of turn effects...'),
        'type' => 'game',
        'args' => 'argPlayerTurn',
        'action' => 'stEndTurn',
        'updateGameProgression' => true,
        'transitions' => array( 'moveGuard' => State::MoveGuard->value, 'chooseAlarm' => State::SpecialChoice->value )
    ),

    State::MoveGuard->value => array(
        'name' => 'moveGuard',
        'description' => clienttranslate('Guard is moving...'),
        'type' => 'game',
        'action' => 'stMoveGuard',
        'updateGameProgression' => true,
        'transitions' => array( 'nextPlayer' => State::NextPlayer->value, 'chooseAlarm' => State::SpecialChoice->value, 'gameOver' => State::GameOver->value )
    ),

    State::NextPlayer->value => array(
        'name' => 'nextPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextPlayer',
        'transitions' => array( 'playerTurn' => State::PlayerTurn->value )
    ),

    State::CardChoice->value => array(
        'name' => 'cardChoice',
        'description' => clienttranslate('${card_name_displayed}: ${actplayer} must choose ${choice_description}'),
        'descriptionmyturn' => clienttranslate('${card_name_displayed}: ${you} must choose ${choice_description}'),
        'type' => 'activeplayer',
        'args' => 'argCardChoice',
        'updateGameProgression' => true,
        'possibleactions' => array( 'selectCardChoice', 'cancelCardChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'tileChoice' => State::TileChoice->value, 'restartTurn' => State::PlayerTurn->value, 'chooseAlarm' => State::SpecialChoice->value, 'gameOver' => State::GameOver->value )
    ),

    State::TileChoice->value => array(
        'name' => 'tileChoice',
        'description' => clienttranslate('${tile_name}: ${actplayer} must choose an option'),
        'descriptionmyturn' => clienttranslate('${tile_name}: ${you} must choose an option'),
        'type' => 'activeplayer',
        'args' => 'argTileChoice',
        'possibleactions' => array( 'selectTileChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'tileChoice' => State::TileChoice->value, 'restartTurn' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'switchRookMove' => State::SwitchRookMove->value, 'chooseAlarm' => State::SpecialChoice->value )
    ),

    State::PlayerChoice->value => array(
        'name' => 'playerChoice',
        'description' => clienttranslate('${actplayer} must choose a player'),
        'descriptionmyturn' => clienttranslate('${you} must choose a player'),
        'type' => 'activeplayer',
        'args' => 'argPlayerChoice',
        'possibleactions' => array( 'selectPlayerChoice', 'cancelPlayerChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'proposeTrade' => State::ProposeTrade->value, 'specialChoice' => State::SpecialChoice->value, 'chooseAlarm' => State::SpecialChoice->value, 'restartTurn' => State::PlayerTurn->value )
    ),

    State::ProposeTrade->value => array(
        'name' => 'proposeTrade',
        'description' => clienttranslate('${actplayer} must choose cards to trade'),
        'descriptionmyturn' => clienttranslate('${you} must choose cards to trade'),
        'type' => 'activeplayer',
        'args' => 'argProposeTrade',
        'possibleactions' => array( 'proposeTrade', 'cancelTrade' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'nextTradePlayer' => State::NextTradePlayer->value, 'endTradeOtherPlayer' => State::EndTradeOtherPlayer->value )
    ),

    State::ConfirmTrade->value => array(
        'name' => 'confirmTrade',
        'description' => clienttranslate('${actplayer} must confirm a trade'),
        'descriptionmyturn' => clienttranslate('${you} must confirm a trade'),
        'type' => 'activeplayer',
        'args' => 'argConfirmTrade',
        'possibleactions' => array( 'confirmTrade', 'cancelTrade' ),
        'transitions' => array( 'endTradeOtherPlayer' => State::EndTradeOtherPlayer->value )
    ),

    State::NextTradePlayer->value => array(
        'name' => 'nextTradePlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextTradePlayer',
        'transitions' => array( 'confirmTrade' => State::ConfirmTrade->value )
    ),

    State::EndTradeOtherPlayer->value => array(
        'name' => 'endTradeOtherPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stEndTradeOtherPlayer',
        'transitions' => array( 'nextAction' => State::PlayerTurn->value )
    ),

    State::SpecialChoice->value => array(
        'name' => 'specialChoice',
        'description' => clienttranslate('${choice_name}: ${actplayer} must choose ${choice_description}'),
        'descriptionmyturn' => clienttranslate('${choice_name}: ${you} must choose ${choice_description}'),
        'type' => 'activeplayer',
        'args' => 'argSpecialChoice',
        'updateGameProgression' => true,
        'possibleactions' => array( 'selectSpecialChoice', 'cancelSpecialChoice' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'tileChoice' => State::TileChoice->value, 'playerTurn' => State::PlayerTurn->value, 'moveGuard' => State::MoveGuard->value, 'chooseAlarm' => State::SpecialChoice->value, 'switchRookMove' => State::SwitchRookMove->value, 'gameOver' => State::GameOver->value )
    ),

    State::EndAction->value => array(
        'name' => 'endAction',
        'description' => '',
        'type' => 'game',
        'action' => 'stEndAction',
        'transitions' => array( 'nextAction' => State::PlayerTurn->value, 'drawTools' => State::DrawToolsAndDiscard->value, 'endTurn' => State::EndTurn->value )
    ),

    State::DrawToolsAndDiscard->value => array(
        'name' => 'drawToolsAndDiscard',
        'description' => clienttranslate('${actplayer} must choose a tool'),
        'descriptionmyturn' => clienttranslate('${you} must choose a tool'),
        'type' => 'activeplayer',
        'args' => 'argDrawToolsAndDiscard',
        'possibleactions' => array( 'keepTool', 'restartTurn' ),
        'transitions' => array( 'drawToolsOtherPlayer' => State::DrawToolsOtherPlayer->value, 'nextAction' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'restartTurn' => State::PlayerTurn->value )
    ),

    State::DrawToolsOtherPlayer->value => array(
        'name' => 'drawToolsOtherPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stDrawToolsOtherPlayer',
        'transitions' => array( 'nextAction' => State::PlayerTurn->value )
    ),

    State::TakeCards->value => array(
        'name' => 'takeCards',
        'description' => clienttranslate('${actplayer} must choose cards to take'),
        'descriptionmyturn' => clienttranslate('${you} must choose cards to take'),
        'type' => 'activeplayer',
        'args' => 'argPlayerTurn',
        'possibleactions' => array( 'confirmTakeCards', 'cancelTakeCards' ),
        'transitions' => array( 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value )
    ),

    State::SwitchRookMove->value => array(
        'name' => 'switchRookMove',
        'description' => '',
        'type' => 'game',
        'action' => 'stSwitchRookMove',
        'transitions' => array( 'confirmRookMove' => State::ConfirmRookMove->value, 'endAction' => State::EndAction->value, 'switchRookMove' => State::SwitchRookMove->value, 'tileChoice' => State::TileChoice->value )
    ),

    State::ConfirmRookMove->value => array(
        'name' => 'confirmRookMove',
        'description' => clienttranslate('${actplayer} must confirm The Rook move'),
        'descriptionmyturn' => clienttranslate('The Rook wants to move you to ${destination_name} on floor ${floor}'),
        'type' => 'activeplayer',
        'args' => 'argConfirmRookMove',
        'possibleactions' => array( 'confirmRookMove', 'cancelRookMove' ),
        'transitions' => array( 'switchRookMove' => State::SwitchRookMove->value, 'gameOver' => State::GameOver->value, 'tileChoice' => State::TileChoice->value, 'chooseAlarm' => State::SpecialChoice->value )
    ),

    State::GameOver->value => array(
        'name' => 'gameOver',
        'description' => clienttranslate('End of game'),
        'type' => 'game',
        'action' => 'stGameOver',
        'transitions' => array( 'endGame' => State::GameEnd->value )
    ),
);
