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
 * states.inc.php: the game state machine, built with GameStateBuilder.
 * State ids live in modules/State.class.php.
 */

use Bga\GameFramework\GameStateBuilder;
use Bga\GameFramework\StateType;

require_once(__DIR__ . '/modules/State.class.php');

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = [
    State::RandomizeWalls->value => GameStateBuilder::create()
        ->name('randomizeWalls')
        ->description(clienttranslate('The administrator of the table can generate new walls for this game'))
        ->descriptionMyTurn(clienttranslate('${you} can generate a new set of walls for this game'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stRandomizeWalls')
        ->possibleActions([ 'randomizeWalls' ])
        ->transitions([ 'startGame' => State::ChooseCharacter->value ])
        ->build(),

    State::ChooseCharacter->value => GameStateBuilder::create()
        ->name('chooseCharacter')
        ->description(clienttranslate('Other players must choose a character'))
        ->descriptionMyTurn(clienttranslate('${you} must choose a character'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->action('stChooseCharacter')
        ->args('argChooseCharacter')
        ->possibleActions([ 'playCard' ])
        ->transitions([ 'chooseCharacter' => State::StartingTile->value, 'nextPlayer' => State::ChooseCharacter->value ])
        ->build(),

    State::StartingTile->value => GameStateBuilder::create()
        ->name('startingTile')
        ->description(clienttranslate('${actplayer} must choose a starting tile'))
        ->descriptionMyTurn(clienttranslate('${you} must choose a starting tile'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argStartingTile')
        ->possibleActions([ 'chooseStartingTile' ])
        ->transitions([ '' => State::PlayerTurn->value ])
        ->build(),

    State::PlayerTurn->value => GameStateBuilder::create()
        ->name('playerTurn')
        ->description(clienttranslate('${actplayer} have ${actions_remaining} actions remaining'))
        ->descriptionMyTurn(clienttranslate('${you} have ${actions_remaining} actions remaining'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argPlayerTurn')
        ->updateGameProgression(true)
        ->possibleActions([ 'hack', 'move', 'peek', 'addSafeDie', 'rollSafeDice', 'playCard', 'characterAction', 'trade', 'pickUpCat', 'takeCards', 'pass', 'escape', 'restartTurn' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'endTurn' => State::EndTurn->value, 'nextPlayer' => State::NextPlayer->value, 'cardChoice' => State::CardChoice->value, 'tileChoice' => State::TileChoice->value, 'playerChoice' => State::PlayerChoice->value, 'proposeTrade' => State::ProposeTrade->value, 'takeCards' => State::TakeCards->value, 'specialChoice' => State::SpecialChoice->value, 'rookChoice' => State::SwitchRookMove->value, 'chooseAlarm' => State::SpecialChoice->value, 'restartTurn' => State::PlayerTurn->value, 'gameOver' => State::GameOver->value ])
        ->build(),

    State::EndTurn->value => GameStateBuilder::create()
        ->name('endTurn')
        ->description(clienttranslate('Triggering end of turn effects...'))
        ->type(StateType::GAME)
        ->action('stEndTurn')
        ->args('argPlayerTurn')
        ->updateGameProgression(true)
        ->transitions([ 'moveGuard' => State::MoveGuard->value, 'chooseAlarm' => State::SpecialChoice->value ])
        ->build(),

    State::MoveGuard->value => GameStateBuilder::create()
        ->name('moveGuard')
        ->description(clienttranslate('Guard is moving...'))
        ->type(StateType::GAME)
        ->action('stMoveGuard')
        ->updateGameProgression(true)
        ->transitions([ 'nextPlayer' => State::NextPlayer->value, 'chooseAlarm' => State::SpecialChoice->value, 'gameOver' => State::GameOver->value ])
        ->build(),

    State::NextPlayer->value => GameStateBuilder::create()
        ->name('nextPlayer')
        ->description('')
        ->type(StateType::GAME)
        ->action('stNextPlayer')
        ->transitions([ 'playerTurn' => State::PlayerTurn->value ])
        ->build(),

    State::CardChoice->value => GameStateBuilder::create()
        ->name('cardChoice')
        ->description(clienttranslate('${card_name_displayed}: ${actplayer} must choose ${choice_description}'))
        ->descriptionMyTurn(clienttranslate('${card_name_displayed}: ${you} must choose ${choice_description}'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argCardChoice')
        ->updateGameProgression(true)
        ->possibleActions([ 'selectCardChoice', 'cancelCardChoice', 'restartTurn' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'tileChoice' => State::TileChoice->value, 'restartTurn' => State::PlayerTurn->value, 'chooseAlarm' => State::SpecialChoice->value, 'gameOver' => State::GameOver->value ])
        ->build(),

    State::TileChoice->value => GameStateBuilder::create()
        ->name('tileChoice')
        ->description(clienttranslate('${tile_name}: ${actplayer} must choose an option'))
        ->descriptionMyTurn(clienttranslate('${tile_name}: ${you} must choose an option'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argTileChoice')
        ->possibleActions([ 'selectTileChoice', 'restartTurn' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'tileChoice' => State::TileChoice->value, 'restartTurn' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'switchRookMove' => State::SwitchRookMove->value, 'chooseAlarm' => State::SpecialChoice->value ])
        ->build(),

    State::PlayerChoice->value => GameStateBuilder::create()
        ->name('playerChoice')
        ->description(clienttranslate('${actplayer} must choose a player'))
        ->descriptionMyTurn(clienttranslate('${you} must choose a player'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argPlayerChoice')
        ->possibleActions([ 'selectPlayerChoice', 'cancelPlayerChoice', 'restartTurn' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'proposeTrade' => State::ProposeTrade->value, 'specialChoice' => State::SpecialChoice->value, 'chooseAlarm' => State::SpecialChoice->value, 'restartTurn' => State::PlayerTurn->value ])
        ->build(),

    State::ProposeTrade->value => GameStateBuilder::create()
        ->name('proposeTrade')
        ->description(clienttranslate('${actplayer} must choose cards to trade'))
        ->descriptionMyTurn(clienttranslate('${you} must choose cards to trade'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argProposeTrade')
        ->possibleActions([ 'proposeTrade', 'cancelTrade' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'nextTradePlayer' => State::NextTradePlayer->value, 'endTradeOtherPlayer' => State::EndTradeOtherPlayer->value ])
        ->build(),

    State::ConfirmTrade->value => GameStateBuilder::create()
        ->name('confirmTrade')
        ->description(clienttranslate('${actplayer} must confirm a trade'))
        ->descriptionMyTurn(clienttranslate('${you} must confirm a trade'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argConfirmTrade')
        ->possibleActions([ 'confirmTrade', 'cancelTrade' ])
        ->transitions([ 'endTradeOtherPlayer' => State::EndTradeOtherPlayer->value ])
        ->build(),

    State::NextTradePlayer->value => GameStateBuilder::create()
        ->name('nextTradePlayer')
        ->description('')
        ->type(StateType::GAME)
        ->action('stNextTradePlayer')
        ->transitions([ 'confirmTrade' => State::ConfirmTrade->value ])
        ->build(),

    State::EndTradeOtherPlayer->value => GameStateBuilder::create()
        ->name('endTradeOtherPlayer')
        ->description('')
        ->type(StateType::GAME)
        ->action('stEndTradeOtherPlayer')
        ->transitions([ 'nextAction' => State::PlayerTurn->value ])
        ->build(),

    State::SpecialChoice->value => GameStateBuilder::create()
        ->name('specialChoice')
        ->description(clienttranslate('${choice_name}: ${actplayer} must choose ${choice_description}'))
        ->descriptionMyTurn(clienttranslate('${choice_name}: ${you} must choose ${choice_description}'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argSpecialChoice')
        ->updateGameProgression(true)
        ->possibleActions([ 'selectSpecialChoice', 'cancelSpecialChoice' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value, 'tileChoice' => State::TileChoice->value, 'playerTurn' => State::PlayerTurn->value, 'moveGuard' => State::MoveGuard->value, 'chooseAlarm' => State::SpecialChoice->value, 'switchRookMove' => State::SwitchRookMove->value, 'gameOver' => State::GameOver->value ])
        ->build(),

    State::EndAction->value => GameStateBuilder::create()
        ->name('endAction')
        ->description('')
        ->type(StateType::GAME)
        ->action('stEndAction')
        ->transitions([ 'nextAction' => State::PlayerTurn->value, 'drawTools' => State::DrawToolsAndDiscard->value, 'endTurn' => State::EndTurn->value ])
        ->build(),

    State::DrawToolsAndDiscard->value => GameStateBuilder::create()
        ->name('drawToolsAndDiscard')
        ->description(clienttranslate('${actplayer} must choose a tool'))
        ->descriptionMyTurn(clienttranslate('${you} must choose a tool'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argDrawToolsAndDiscard')
        ->possibleActions([ 'keepTool', 'restartTurn' ])
        ->transitions([ 'drawToolsOtherPlayer' => State::DrawToolsOtherPlayer->value, 'nextAction' => State::PlayerTurn->value, 'endTurn' => State::EndTurn->value, 'restartTurn' => State::PlayerTurn->value ])
        ->build(),

    State::DrawToolsOtherPlayer->value => GameStateBuilder::create()
        ->name('drawToolsOtherPlayer')
        ->description('')
        ->type(StateType::GAME)
        ->action('stDrawToolsOtherPlayer')
        ->transitions([ 'nextAction' => State::PlayerTurn->value ])
        ->build(),

    State::TakeCards->value => GameStateBuilder::create()
        ->name('takeCards')
        ->description(clienttranslate('${actplayer} must choose cards to take'))
        ->descriptionMyTurn(clienttranslate('${you} must choose cards to take'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argPlayerTurn')
        ->possibleActions([ 'confirmTakeCards', 'cancelTakeCards' ])
        ->transitions([ 'endAction' => State::EndAction->value, 'nextAction' => State::PlayerTurn->value ])
        ->build(),

    State::SwitchRookMove->value => GameStateBuilder::create()
        ->name('switchRookMove')
        ->description('')
        ->type(StateType::GAME)
        ->action('stSwitchRookMove')
        ->transitions([ 'confirmRookMove' => State::ConfirmRookMove->value, 'endAction' => State::EndAction->value, 'switchRookMove' => State::SwitchRookMove->value, 'tileChoice' => State::TileChoice->value ])
        ->build(),

    State::ConfirmRookMove->value => GameStateBuilder::create()
        ->name('confirmRookMove')
        ->description(clienttranslate('${actplayer} must confirm The Rook move'))
        ->descriptionMyTurn(clienttranslate('The Rook wants to move you to ${destination_name} on floor ${floor}'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argConfirmRookMove')
        ->possibleActions([ 'confirmRookMove', 'cancelRookMove' ])
        ->transitions([ 'switchRookMove' => State::SwitchRookMove->value, 'gameOver' => State::GameOver->value, 'tileChoice' => State::TileChoice->value, 'chooseAlarm' => State::SpecialChoice->value ])
        ->build(),

    State::GameOver->value => GameStateBuilder::create()
        ->name('gameOver')
        ->description(clienttranslate('End of game'))
        ->type(StateType::GAME)
        ->action('stGameOver')
        ->transitions([ 'endGame' => State::GameEnd->value ])
        ->build(),
];
