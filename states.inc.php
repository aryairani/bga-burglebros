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
    State::RANDOMIZE_WALLS => array(
        'name' => 'randomizeWalls',
        'description' => clienttranslate('The administrator of the table can generate new walls for this game'),
        'descriptionmyturn' => clienttranslate('${you} can generate a new set of walls for this game'),
        'type' => 'activeplayer',
        'action' => 'stRandomizeWalls',
        'possibleactions' => array( 'randomizeWalls' ),
        'transitions' => array( 'startGame' => State::CHOOSE_CHARACTER )
    ),

    State::CHOOSE_CHARACTER => array(
        'name' => 'chooseCharacter',
        'description' => clienttranslate('Other players must choose a character'),
        'descriptionmyturn' => clienttranslate('${you} must choose a character'),
        'type' => 'multipleactiveplayer',
        'action' => 'stChooseCharacter',
        'args' => 'argChooseCharacter',
        'possibleactions' => array( 'playCard' ),
        'transitions' => array( 'chooseCharacter' => State::STARTING_TILE, 'nextPlayer' => State::CHOOSE_CHARACTER )
    ),

    State::STARTING_TILE => array(
        'name' => 'startingTile',
        'description' => clienttranslate('${actplayer} must choose a starting tile'),
        'descriptionmyturn' => clienttranslate('${you} must choose a starting tile'),
        'type' => 'activeplayer',
        'args' => 'argStartingTile',
        'possibleactions' => array( 'chooseStartingTile' ),
        'transitions' => array( '' => State::PLAYER_TURN )
    ),

    State::PLAYER_TURN => array(
        'name' => 'playerTurn',
        'description' => clienttranslate('${actplayer} have ${actions_remaining} actions remaining'),
        'descriptionmyturn' => clienttranslate('${you} have ${actions_remaining} actions remaining'),
        'type' => 'activeplayer',
        // 'action' => 'stPlayerTurn',
        'args' => 'argPlayerTurn',
        'updateGameProgression' => true,
        'possibleactions' => array( 'hack', 'move', 'peek', 'addSafeDie', 'rollSafeDice', 'playCard', 'characterAction', 'trade', 'pickUpCat', 'takeCards', 'pass', 'escape', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'endTurn' => State::END_TURN, 'nextPlayer' => State::NEXT_PLAYER, 'cardChoice' => State::CARD_CHOICE, 'tileChoice' => State::TILE_CHOICE, 'playerChoice' => State::PLAYER_CHOICE, 'proposeTrade' => State::PROPOSE_TRADE, 'takeCards' => State::TAKE_CARDS, 'specialChoice' => State::SPECIAL_CHOICE, 'rookChoice' => State::SWITCH_ROOK_MOVE, 'chooseAlarm' => State::SPECIAL_CHOICE, 'restartTurn' => State::PLAYER_TURN, 'gameOver' => State::GAME_OVER )
    ),

    State::END_TURN => array(
        'name' => 'endTurn',
        'description' => clienttranslate('Triggering end of turn effects...'),
        'type' => 'game',
        'args' => 'argPlayerTurn',
        'action' => 'stEndTurn',
        'updateGameProgression' => true,
        'transitions' => array( 'moveGuard' => State::MOVE_GUARD, 'chooseAlarm' => State::SPECIAL_CHOICE )
    ),

    State::MOVE_GUARD => array(
        'name' => 'moveGuard',
        'description' => clienttranslate('Guard is moving...'),
        'type' => 'game',
        'action' => 'stMoveGuard',
        'updateGameProgression' => true,
        'transitions' => array( 'nextPlayer' => State::NEXT_PLAYER, 'chooseAlarm' => State::SPECIAL_CHOICE, 'gameOver' => State::GAME_OVER )
    ),

    State::NEXT_PLAYER => array(
        'name' => 'nextPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextPlayer',
        'transitions' => array( 'playerTurn' => State::PLAYER_TURN )
    ),

    State::CARD_CHOICE => array(
        'name' => 'cardChoice',
        'description' => clienttranslate('${card_name_displayed}: ${actplayer} must choose ${choice_description}'),
        'descriptionmyturn' => clienttranslate('${card_name_displayed}: ${you} must choose ${choice_description}'),
        'type' => 'activeplayer',
        'args' => 'argCardChoice',
        'updateGameProgression' => true,
        'possibleactions' => array( 'selectCardChoice', 'cancelCardChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'nextAction' => State::PLAYER_TURN, 'endTurn' => State::END_TURN, 'tileChoice' => State::TILE_CHOICE, 'restartTurn' => State::PLAYER_TURN, 'chooseAlarm' => State::SPECIAL_CHOICE, 'gameOver' => State::GAME_OVER )
    ),

    State::TILE_CHOICE => array(
        'name' => 'tileChoice',
        'description' => clienttranslate('${tile_name}: ${actplayer} must choose an option'),
        'descriptionmyturn' => clienttranslate('${tile_name}: ${you} must choose an option'),
        'type' => 'activeplayer',
        'args' => 'argTileChoice',
        'possibleactions' => array( 'selectTileChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'tileChoice' => State::TILE_CHOICE, 'restartTurn' => State::PLAYER_TURN, 'endTurn' => State::END_TURN, 'switchRookMove' => State::SWITCH_ROOK_MOVE, 'chooseAlarm' => State::SPECIAL_CHOICE )
    ),

    State::PLAYER_CHOICE => array(
        'name' => 'playerChoice',
        'description' => clienttranslate('${actplayer} must choose a player'),
        'descriptionmyturn' => clienttranslate('${you} must choose a player'),
        'type' => 'activeplayer',
        'args' => 'argPlayerChoice',
        'possibleactions' => array( 'selectPlayerChoice', 'cancelPlayerChoice', 'restartTurn' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'nextAction' => State::PLAYER_TURN, 'proposeTrade' => State::PROPOSE_TRADE, 'specialChoice' => State::SPECIAL_CHOICE, 'chooseAlarm' => State::SPECIAL_CHOICE, 'restartTurn' => State::PLAYER_TURN )
    ),

    State::PROPOSE_TRADE => array(
        'name' => 'proposeTrade',
        'description' => clienttranslate('${actplayer} must choose cards to trade'),
        'descriptionmyturn' => clienttranslate('${you} must choose cards to trade'),
        'type' => 'activeplayer',
        'args' => 'argProposeTrade',
        'possibleactions' => array( 'proposeTrade', 'cancelTrade' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'nextAction' => State::PLAYER_TURN, 'nextTradePlayer' => State::NEXT_TRADE_PLAYER, 'endTradeOtherPlayer' => State::END_TRADE_OTHER_PLAYER )
    ),

    State::CONFIRM_TRADE => array(
        'name' => 'confirmTrade',
        'description' => clienttranslate('${actplayer} must confirm a trade'),
        'descriptionmyturn' => clienttranslate('${you} must confirm a trade'),
        'type' => 'activeplayer',
        'args' => 'argConfirmTrade',
        'possibleactions' => array( 'confirmTrade', 'cancelTrade' ),
        'transitions' => array( 'endTradeOtherPlayer' => State::END_TRADE_OTHER_PLAYER )
    ),

    State::NEXT_TRADE_PLAYER => array(
        'name' => 'nextTradePlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextTradePlayer',
        'transitions' => array( 'confirmTrade' => State::CONFIRM_TRADE )
    ),

    State::END_TRADE_OTHER_PLAYER => array(
        'name' => 'endTradeOtherPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stEndTradeOtherPlayer',
        'transitions' => array( 'nextAction' => State::PLAYER_TURN )
    ),

    State::SPECIAL_CHOICE => array(
        'name' => 'specialChoice',
        'description' => clienttranslate('${choice_name}: ${actplayer} must choose ${choice_description}'),
        'descriptionmyturn' => clienttranslate('${choice_name}: ${you} must choose ${choice_description}'),
        'type' => 'activeplayer',
        'args' => 'argSpecialChoice',
        'updateGameProgression' => true,
        'possibleactions' => array( 'selectSpecialChoice', 'cancelSpecialChoice' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'nextAction' => State::PLAYER_TURN, 'tileChoice' => State::TILE_CHOICE, 'playerTurn' => State::PLAYER_TURN, 'moveGuard' => State::MOVE_GUARD, 'chooseAlarm' => State::SPECIAL_CHOICE, 'switchRookMove' => State::SWITCH_ROOK_MOVE, 'gameOver' => State::GAME_OVER )
    ),

    State::END_ACTION => array(
        'name' => 'endAction',
        'description' => '',
        'type' => 'game',
        'action' => 'stEndAction',
        'transitions' => array( 'nextAction' => State::PLAYER_TURN, 'drawTools' => State::DRAW_TOOLS_AND_DISCARD, 'endTurn' => State::END_TURN )
    ),

    State::DRAW_TOOLS_AND_DISCARD => array(
        'name' => 'drawToolsAndDiscard',
        'description' => clienttranslate('${actplayer} must choose a tool'),
        'descriptionmyturn' => clienttranslate('${you} must choose a tool'),
        'type' => 'activeplayer',
        'args' => 'argDrawToolsAndDiscard',
        'possibleactions' => array( 'keepTool', 'restartTurn' ),
        'transitions' => array( 'drawToolsOtherPlayer' => State::DRAW_TOOLS_OTHER_PLAYER, 'nextAction' => State::PLAYER_TURN, 'endTurn' => State::END_TURN, 'restartTurn' => State::PLAYER_TURN )
    ),

    State::DRAW_TOOLS_OTHER_PLAYER => array(
        'name' => 'drawToolsOtherPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stDrawToolsOtherPlayer',
        'transitions' => array( 'nextAction' => State::PLAYER_TURN )
    ),

    State::TAKE_CARDS => array(
        'name' => 'takeCards',
        'description' => clienttranslate('${actplayer} must choose cards to take'),
        'descriptionmyturn' => clienttranslate('${you} must choose cards to take'),
        'type' => 'activeplayer',
        'args' => 'argPlayerTurn',
        'possibleactions' => array( 'confirmTakeCards', 'cancelTakeCards' ),
        'transitions' => array( 'endAction' => State::END_ACTION, 'nextAction' => State::PLAYER_TURN )
    ),

    State::SWITCH_ROOK_MOVE => array(
        'name' => 'switchRookMove',
        'description' => '',
        'type' => 'game',
        'action' => 'stSwitchRookMove',
        'transitions' => array( 'confirmRookMove' => State::CONFIRM_ROOK_MOVE, 'endAction' => State::END_ACTION, 'switchRookMove' => State::SWITCH_ROOK_MOVE, 'tileChoice' => State::TILE_CHOICE )
    ),

    State::CONFIRM_ROOK_MOVE => array(
        'name' => 'confirmRookMove',
        'description' => clienttranslate('${actplayer} must confirm The Rook move'),
        'descriptionmyturn' => clienttranslate('The Rook wants to move you to ${destination_name} on floor ${floor}'),
        'type' => 'activeplayer',
        'args' => 'argConfirmRookMove',
        'possibleactions' => array( 'confirmRookMove', 'cancelRookMove' ),
        'transitions' => array( 'switchRookMove' => State::SWITCH_ROOK_MOVE, 'gameOver' => State::GAME_OVER, 'tileChoice' => State::TILE_CHOICE, 'chooseAlarm' => State::SPECIAL_CHOICE )
    ),

    State::GAME_OVER => array(
        'name' => 'gameOver',
        'description' => clienttranslate('End of game'),
        'type' => 'game',
        'action' => 'stGameOver',
        'transitions' => array( 'endGame' => State::GAME_END )
    ),
);
