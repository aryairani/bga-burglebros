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
  * burglebros.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */

use Bga\GameFramework\Components\Deck;
use Bga\GameFramework\Table;
use Bga\GameFramework\VisibleSystemException;

require_once("modules/BurgleBrosBoard.class.php");
require_once("modules/BurgleBrosWallLayouts.class.php");
require_once("modules/CardType.class.php");
require_once("modules/TileType.class.php");
require_once("modules/GameStateValue.class.php");
require_once("modules/TokenType.class.php");
require_once("modules/DeckType.class.php");
require_once("modules/CardFace.class.php");
require_once("modules/CardInfo.class.php");
require_once("modules/Scenario.class.php");
require_once("modules/State.class.php");
require_once("modules/PlayerChoice.class.php");
require_once("modules/SpecialChoice.class.php");
require_once("modules/GameOption.class.php");
require_once("modules/BurgleBrosGrid.class.php");
require_once("modules/BurgleBrosFloorPlan.class.php");
require_once("modules/BurgleBrosPathfinder.class.php");
require_once("modules/BurgleBrosTilePosition.class.php");


class burglebros extends Table
{
        public BurgleBrosBoard $board;
        public Deck $cards;
        public Deck $tiles;
        public Deck $tokens;     

    // Static game material, assigned by material.inc.php (included in the framework constructor).
    /** @var array<int, DeckType> deck descriptors by card type (0-3) */
    public array $card_types;
    /** @var array<int, list<CardInfo>> card definitions by card type; list index + 1 == type_arg */
    public array $card_info;
    /** @var array<int, DeckType> deck descriptors by card type (4-6, one patrol deck per floor) */
    public array $patrol_types;
    /** @var array<string, list<int>> tile type => safe die number of each copy */
    public array $tile_types;
    /** @var array<string, list<int|false>> tile type => safe die number of each copy; false = copy not used in the Office Job */
    public array $tile_types_office_job;
    /** @var array<string, array{name: string, nb: int}> tile type => display name and copy count */
    public array $tile_distribution;
    /** @var array<string, array{name: string, nb: int}> tile type => display name and copy count */
    public array $tile_distribution_office_job;
    /** @var list<array{name: string, color: string}> */
    public array $token_types;
    /** @var list<string> */
    public array $player_choices;
    /** @var list<string> */
    public array $special_choices;
    /** @var array<int, string> state id => state to resume after chooseAlarm */
    public array $state_after_alarms;


	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(GameStateValue::labels());

        // Initialize module classes
        $this->board = new BurgleBrosBoard($this);

        $this->cards = $this->deckFactory->createDeck( "card" );
        $this->tiles = $this->deckFactory->createDeck( "tile" );
        $this->tokens = $this->deckFactory->createDeck( "token" );     
	}

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {
        // Setup board and walls
        $this->board->setupNewGame($players, $options);

        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $option_level = Level::from((int) $options[GameOption::Level->value]);
        $option_stealth_count = match ($option_level) {
            Level::Easy => 6,
            Level::Normal => 3,
            Level::Hard => 1,
        };
        $sql = "INSERT INTO player (player_id, player_color, player_name, player_stealth_tokens) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".addslashes( $player['player_name'] )."',$option_stealth_count)";
        }
        $sql .= implode(',', $values);
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();

        // Initialize solo_characters table if needed
        $multi_characters = $this->getSoloMultiCharacters();
        // $multi_characters = 1;
        $current_player_id = 0;
        if ($multi_characters > 1) {
            $id = key($players);
            $player = $players[$id];
            $current_player_id = $id;
            $currentPlayerColor = $this->loadPlayersBasicInfos()[$current_player_id]['player_color'];
            $default_colors = array_diff($gameinfos['player_colors'], [$currentPlayerColor]);
            $values = array();
            $sql = "INSERT INTO solo_characters (player_id, player_color, player_name, player_avatar) VALUES ";
            for ($i=1; $i <= $multi_characters; $i++) {
                $color = array_shift( $default_colors );
                $values[] = "('".$id++."','$color','".addslashes( $player['player_name'] )."$i','".addslashes( $player['player_avatar'] )."')";
            }
            $sql .= implode(',', $values);
            self::DbQuery( $sql );
            $players = $this->loadPlayersInfos();
        }

        /************ Start the game initialization *****/

        // Init global values with their initial values
        $this->initStateValue(GameStateValue::ActionsRemaining, 4 );
        $this->initStateValue(GameStateValue::SafeDieCount1, 0 );
        $this->initStateValue(GameStateValue::SafeDieCount2, 0 );
        $this->initStateValue(GameStateValue::SafeDieCount3, 0 );
        $this->initStateValue(GameStateValue::MotionTileEntered, 0x000 ); // Bit vector
        // Fort Knox starts guard die at 3 on floor 1
        $intial_guard_die = $this->scenario() === Scenario::FortKnox ? 3 : 2;
        $this->initStateValue(GameStateValue::PatrolDieCount1, $intial_guard_die++ );
        $this->initStateValue(GameStateValue::PatrolDieCount2, $intial_guard_die++ );
        $this->initStateValue(GameStateValue::PatrolDieCount3, $intial_guard_die );
        $this->initStateValue(GameStateValue::LaboratoryTileEntered, 0x000 ); // Bit vector
        $this->initStateValue(GameStateValue::InvisibleSuitActive, 0 );
        $this->initStateValue(GameStateValue::EmpPlayer, 0 );
        $this->initStateValue(GameStateValue::CardChoice, 0 );
        $this->initStateValue(GameStateValue::CharacterAbilityUsed, 0 );
        $this->initStateValue(GameStateValue::AcrobatEnteredGuardTile, 0 );
        $this->initStateValue(GameStateValue::TileChoice, 0 );
        $this->initStateValue(GameStateValue::MotionTileExitChoice, 0 );
        $this->initStateValue(GameStateValue::PlayerChoice, PlayerChoice::None->value );
        $this->initStateValue(GameStateValue::PlayerChoiceArg, 0 );
        $this->initStateValue(GameStateValue::FirstAction, 1 );
        $this->initStateValue(GameStateValue::DrawToolsPlayer, 0 );
        $this->initStateValue(GameStateValue::DrawToolsNextPlayer, 0 );
        $this->initStateValue(GameStateValue::StealthDepleted, 0 );
        $this->initStateValue(GameStateValue::PlayerPass, 0 );
        $this->initStateValue(GameStateValue::DropLoot, 0 );
        $this->initStateValue(GameStateValue::UndoAllowed, 0 );
        $this->initStateValue(GameStateValue::RookDestinationTile, 0 );
        $this->initStateValue(GameStateValue::CurrentPlayer, $current_player_id );
        $this->initStateValue(GameStateValue::RemainingMoves, 0 );
        $this->initStateValue(GameStateValue::StateAfterAlarm, 0 );
        $this->initStateValue(GameStateValue::MoveDecreaseAfterAlarm, 0 );
        $this->initStateValue(GameStateValue::DonutsDropped, 0 );
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        self::initStat( "table", "turns_number", 0 );
        self::initStat( "table", "tiles_unflipped", 0 );
        self::initStat( "table", "event_cards", 0 );
        self::initStat( "table", "alarm_triggered", 0 );

        self::initStat( "player", "turns_number", 0 );
        self::initStat( "player", "tools_drawn", 0 );
        self::initStat( "player", "tools_used", 0 );
        self::initStat( "player", "stealth_remaining", 0 );
        self::initStat( "player", "trade_confirmed", 0 );
        self::initStat( "player", "special_ability_use", 0 );

        $option_character = $options[GameOption::CharacterAssignment->value];
        $option_characters_advanced = $option_character == CharacterAssignment::RandomAdvanced->value || $option_character == CharacterAssignment::ChoiceAdvanced->value;
        $card_info = $this->card_info;
        if (!$option_characters_advanced) {
            // Drop the advanced character variants (names ending in '2'). array_filter keeps the
            // original keys, so the surviving cards' type_args still match the full material list.
            $card_info[CardType::Character->value] = array_filter(
                $card_info[CardType::Character->value],
                function ($card) { return substr($card->name, -1) != '2'; });
        }
        $this->createDecks($this->card_types, $card_info);
        $this->createDecks($this->patrol_types, $this->patrolInfo());
        if ($this->getSquareSize() != 4) {
            // Move Patrol Cards for shaft position out of play
            $floor_count = $this->getFloorCount();
            $shaft_position = $this->board->getShaftPosition();
            for ($i=1; $i <= $floor_count; $i++) {
                $ids = array_keys($this->cards->getCardsOfType(CardType::patrol($i)->value, $shaft_position + 1)); // card_type_arg == shaft +1
                $this->cards->moveCards($ids, 'patrol_oop');
            }
        }

        // Guards
        $tokens = array ();
        $max_floor = $this->getFloorCount();
        for ($floor=1; $floor <= $max_floor; $floor++) { 
            $tokens [] = array('type' => TokenType::Guard->value, 'type_arg' => $floor, 'nbr' => 1);
            $tokens [] = array('type' => TokenType::Patrol->value, 'type_arg' => $floor, 'nbr' => 1);
            $tokens [] = array('type' => TokenType::Crack->value, 'type_arg' => $floor, 'nbr' => 1);    # when a first die is added on the safe
        }
        // Fort Knox, create other tokens for the third safe
        if ($this->scenario() === Scenario::FortKnox) {
            $tokens [] = array('type' => TokenType::Crack->value, 'type_arg' => 1, 'nbr' => 1);
            $tokens [] = array('type' => TokenType::Crack->value, 'type_arg' => 2, 'nbr' => 1);
        }
        $tokens [] = array('type' => TokenType::Hack->value, 'type_arg' => 0, 'nbr' => 19);
        $tokens [] = array('type' => TokenType::Safe->value, 'type_arg' => 0, 'nbr' => 22); # when a tile is validated by a safe roll
        // $tokens [] = array('type' => TokenType::Die->value, 'type_arg' => 0, 'nbr' => 21);  # store die values when rolling (handle stethoscope)
        $tokens [] = array('type' => TokenType::Stealth->value, 'type_arg' => 0, 'nbr' => 50);
        $tokens [] = array('type' => TokenType::Alarm->value, 'type_arg' => 0, 'nbr' => 45); # max 15 alarms per floor
        $tokens [] = array('type' => TokenType::Open->value, 'type_arg' => 0, 'nbr' => 6);  # when a safe or keypad tile is opened
        $tokens [] = array('type' => TokenType::Keypad->value, 'type_arg' => 0, 'nbr' => 6); # number of attemps on a safe (4 should be enough with 5 actions max but you never know)
        $tokens [] = array('type' => TokenType::Stairs->value, 'type_arg' => 0, 'nbr' => 3);
        $tokens [] = array('type' => TokenType::Thermal->value, 'type_arg' => 0, 'nbr' => 2);
        $tokens [] = array('type' => TokenType::Crowbar->value, 'type_arg' => 0, 'nbr' => 1);
        $tokens [] = array('type' => TokenType::Crow->value, 'type_arg' => 0, 'nbr' => 1);
        $tokens [] = array('type' => TokenType::Cat->value, 'type_arg' => 0, 'nbr' => 1);
        $this->tokens->createCards( $tokens );

        // Remove cards that don't make sense for the number of players
        if (count($players) == 1 && $multi_characters <= 1) {
            $this->moveCardsOutOfPlay(DeckType::Loot, 'gold-bar');
            $this->moveCardsOutOfPlay(DeckType::Characters, 'rook1');
            $this->moveCardsOutOfPlay(DeckType::Characters, 'rook2');
            $this->moveCardsOutOfPlay(DeckType::Events, 'freight-elevator');
            $this->moveCardsOutOfPlay(DeckType::Events, 'buddy-system');
            $this->moveCardsOutOfPlay(DeckType::Events, 'dead-drop');
            $this->moveCardsOutOfPlay(DeckType::Events, 'jump-the-gun');
        }
        
        foreach ($players as $player_id => $player) {
            $player_token = array('type' => TokenType::Player->value, 'type_arg' => $player_id, 'nbr' => 1);
            $this->tokens->createCards(array($player_token), 'hand', $player_id);
            if ($option_character == CharacterAssignment::Random->value || $option_character == CharacterAssignment::RandomAdvanced->value) {
                $character = $this->cards->pickCard(DeckType::Characters->deckName(), $player_id);
                if ($option_character == CharacterAssignment::RandomAdvanced->value) {
                    // Move advanced card to player hand so they can choose on the next state
                    $type_arg = $character['type_arg'] % 2 == 0 ? $character['type_arg'] - 1: $character['type_arg'] + 1;
                    $advanced_character_id = key($this->cards->getCardsOfType(CardType::Character->value,$type_arg));
                    $this->cards->moveCard($advanced_character_id, 'hand', $player_id);
                } elseif ($this->getCardType($character) == 'rigger1') {
                    $type_arg = $this->getCardTypeForName(CardType::Tool,'dynamite');
                    $dynamite = array_values($this->cards->getCardsOfType(CardType::Tool->value,$type_arg))[0];
                    $this->cards->moveCard($dynamite['id'], 'hand', $player_id);
                }
            }

            $this->pickTokens(TokenType::Stealth, 'player', $player_id, $option_stealth_count);
        }

        // Activate table administrator to randomize walls if needed
        if ($this->bga->tableOptions->get(GameOption::Walls->value) !== Walls::Default->value) {
            foreach ($players as $id => $player) {
                if (isset($player['player_is_admin']) && $player['player_is_admin'] == 1) {
                    $admin_id = $id;
                }
            }
            if (isset($admin_id)) {
                $this->gamestate->changeActivePlayer( $admin_id );
            }
        }
        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        // Move starting guard
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, 1))[0];
        $this->setupPatrol($guard_token, 1);

        /************ End of the game initialization *****/
        return State::RandomizeWalls->value;
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = [];
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        // $sql = "SELECT player_id id, player_score score, player_stealth_tokens stealth_tokens FROM player ";
        $result['players'] = $this->loadPlayersInfos();
        foreach ($result['players'] as $player_id => &$player) {
            $player['hand'] = $this->cards->getPlayerHand($player_id);
            $player['character'] = $this->getPlayerCharacter($player_id);
            if ($player['character'])
                $player['character']['name'] = $this->getCardType($player['character']);
            $player_token = $this->getPlayerToken($player_id);
            $player['escaped'] = $player_token['location'] == 'roof';
        }

        $result = array_merge($result, $this->gatherCardData('card', $this->card_types, $this->card_info));
        $result = array_merge($result, $this->gatherCardData('patrol', $this->patrol_types, $this->patrolInfo()));
        $result['card_info'] = $this->card_info;
        $result['floor_count'] = $this->getFloorCount();
        $result['square_size'] = $this->getSquareSize();
        $result['shaft_position'] = $this->board->getShaftPosition();
        $result['patrol_names'] = $this->patrolNames();
        $result['solo_characters'] = $this->getSoloMultiCharacters();
        $result['active_player_id'] = $this->getCurrentPlayerIdCustom();

        $result['tile_distribution'] = $this->scenario() === Scenario::OfficeJob ? $this->tile_distribution_office_job : $this->tile_distribution;
        $result['flipped_tiles'] = $this->getFlippedTiles();

        $tokens = array();
        foreach ( $this->token_types as $index => $desc ) {
            $tokens[$desc['name']] = array('id'=> $index, 'color' => $desc['color']);
        }
        $result['token_types'] = $tokens;

        $max_floor = $this->getFloorCount();
        for ($i=1; $i <= $max_floor; $i++) { 
            $result["floor$i"] = $this->getTiles($i);
            $result["patrol_counters"][$i] = $this->cards->countCardInLocation(DeckType::patrol($i)->deckName());
        }
        $result['walls'] = $this->getWalls();

        $result['guard_tokens'] = $this->tokensOfType(TokenType::Guard);
        $result['player_tokens'] = $this->tokensOfType(TokenType::Player);
        $result['generic_tokens'] = $this->getGenericTokens();
        $result['card_tokens'] = $this->getCardTokens();
        
        $safe_tokens = $this->tokensOfType(TokenType::Crack);
        foreach ($safe_tokens as $id => &$value) {
            $safe_id = $value['location_arg'];
            $value['die_num'] = $this->getSafeDie($safe_id);
        }
        $result['crack_tokens'] = $safe_tokens;

        $patrol_tokens = $this->tokensOfType(TokenType::Patrol);
        $guard_paths = [];
        $tiles = self::getCollectionFromDB("SELECT card_id id, card_location_arg location_arg FROM tile");
        foreach ($patrol_tokens as $id => &$value) {
            $floor = $value['type_arg'];
            $value['die_num'] = $this->stateValue(GameStateValue::patrolDieCount($floor));
            $guard_paths["floor$floor"] = $value['location'] == 'deck' ? null : $this->getPathByLocation($floor, $tiles);
        }
        $result['patrol_tokens'] = $patrol_tokens;
        $result['guard_paths'] = $guard_paths;
        $result['undo_allowed'] = $this->stateValue(GameStateValue::UndoAllowed);

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        if ($this->stateValue(GameStateValue::StealthDepleted) || $this->allPlayersEscaped()) {
            return 100;
        } else {
            return $this->openSafes() * 25;
        }
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    // Typed accessors for the game state values registered in initGameStateLabels
    public function stateValue(GameStateValue $v): int {
        return (int) $this->getGameStateValue($v->value);
    }

    public function setStateValue(GameStateValue $v, int|BackedEnum $value): void {
        $this->setGameStateValue($v->value, $value instanceof BackedEnum ? $value->value : $value);
    }

    public function incStateValue(GameStateValue $v, int $increment): void {
        $this->incGameStateValue($v->value, $increment);
    }

    public function initStateValue(GameStateValue $v, int|BackedEnum $value): void {
        $this->setGameStateInitialValue($v->value, $value instanceof BackedEnum ? $value->value : $value);
    }

    /** null while the scenario value is still 0 (alpha games created before the option existed) */
    public function scenario(): ?Scenario {
        return Scenario::tryFrom($this->stateValue(GameStateValue::Scenario));
    }

    // Typed fronts for the token deck
    public function tokensOfType(TokenType $type, $type_arg = null) {
        return $this->tokens->getCardsOfType($type->value, $type_arg);
    }

    public function tokensOfTypeInLocation(TokenType $type, $type_arg, $location, $location_arg = null) {
        return $this->tokens->getCardsOfTypeInLocation($type->value, $type_arg, $location, $location_arg);
    }

    public function getSoloMultiCharacters() {
        return $this->bga->tableOptions->get(GameOption::SoloMultiCharacters->value) ?? 1;
    }

    public function loadPlayersInfos() {
        if ($this->getSoloMultiCharacters() > 1) {
            return self::getCollectionFromDB("SELECT player_id id, player_name, player_color, player_avatar, player_score score, player_stealth_tokens stealth_tokens FROM solo_characters");            
        } else {
            return self::loadPlayersBasicInfos();
        }
    }

    public function getCurrentPlayerIdCustom() {
        if ($this->getSoloMultiCharacters() > 1) {
            return $this->stateValue(GameStateValue::CurrentPlayer);
        } else {
            return self::getCurrentPlayerId();
        }
    }
    public function getActivePlayerIdCustom() {
        if ($this->getSoloMultiCharacters() > 1) {
            return $this->getCurrentPlayerIdCustom();
        } else {
            return self::getActivePlayerId();
        }
    }
    public function getActivePlayerNameCustom() {
        if ($this->getSoloMultiCharacters() > 1) {
            $players = $this->loadPlayersInfos();
            $active_player_id = $this->getCurrentPlayerIdCustom();
            return $players[$active_player_id]['player_name'];
        } else {
            return self::getActivePlayerName();
        }
    }
    public function getPlayerBeforeCustom($player_id) {
        $escaped_players = $this->getEscapedPlayers();
        $multi_characters = $this->getSoloMultiCharacters();
        $security_loop = 10; // 4 should be enough because only 4 players but, well, you never know
        while ($security_loop > 0) {
            if ($multi_characters > 1) {
                $human_player_id = self::getCurrentPlayerId();
                if ($human_player_id == $player_id) {
                    $player_before = $human_player_id + $multi_characters - 1;
                } else {
                    $player_before = --$player_id;
                }
            } else {
                $player_before = self::getPlayerBefore($player_id);
            }
            if (!in_array($player_before, $escaped_players)) {
                return $player_before;
            }
            --$security_loop;
        }
        return $player_id;
    }

    public function activeNextPlayerCustom() {
        $multi_characters = $this->getSoloMultiCharacters();
        if ($multi_characters > 1) {
            $next_player_id = $this->getPlayerAfterCustom();
            $this->setStateValue(GameStateValue::CurrentPlayer, $next_player_id);
            $this->bga->notify->all('activatePlayer', '', [
                'player_id' => $next_player_id,
            ]);
            return $next_player_id;
        } else {
            return self::activeNextPlayer();
        }
    }

    function getPlayerAfterCustom() {
        $multi_characters = $this->getSoloMultiCharacters();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        if ($multi_characters > 1) {
            $human_player_id = self::getCurrentPlayerId();
            // Increase player_id or loop back to human id
            $current_player_id = ++$current_player_id >= $human_player_id + $multi_characters ? $human_player_id : $current_player_id;
            return $current_player_id;
        } else {
            return self::getPlayerAfter($current_player_id);
        }
    }

    function getAvailableCharacters() {
        // self::getCurrentPlayerId() may raise an exception error because this is the first state called by game setup, so wrap it into a try / catch
        try {
            $current_player_id = $this->getCurrentPlayerIdCustom();
            $cards = $this->cards->getCardsOfTypeInLocation(CardType::Character->value,null, 'hand', $current_player_id);
        } catch (Exception $e) {
            // No one has chosen a character yet (game not started)
            $cards = $this->cards->getCardsOfTypeInLocation(CardType::Character->value,null, 'hand');
        }
        // If hand is empty, player can choose any available character
        if (count($cards) == 0) {
            $cards = self::getCollectionFromDB("SELECT card_id id, card_type type, card_type_arg type_arg FROM card WHERE card_location='characters_deck'");
        }
        return $cards;
    }

    public function getFloorCount() {
        // Return the number of floors (3 for the Bank job, 2 otherwise)
        // TODO Clean up the null check when all the alpha games are done
        if ($this->scenario() !== null && $this->scenario() !== Scenario::BankJob) {
            return 2;
        } else {
            return 3;
        }
    }

    public function getSquareSize() {
        // Return the square size of the board (5 for Fort Knox scenario, 4 otherwise)
        if ($this->scenario() === Scenario::FortKnox) {
            return 5;
        } else {
            return 4;
        }
    }

    /** @return list<array{name: string}> patrol card faces in row-major order ("A1".."D4" or "A1".."E5" per square size) */
public function patrolNames(): array {
        static $cache = array();
        $width = $this->getSquareSize();
        if (!isset($cache[$width])) {
            $names = array();
            for ($row = 1; $row <= $width; $row++) {
                for ($col = 0; $col < $width; $col++) {
                    $names[] = array('name' => chr(ord('A') + $col) . $row);
                }
            }
            $cache[$width] = $names;
        }
        return $cache[$width];
    }

    /** @return array<int, list<CardFace>> patrol card faces by card type (4-6) */
    public function patrolInfo(): array {
        $faces = array_map(fn($face) => new CardFace($face['name']), $this->patrolNames());
        return array(
            CardType::patrol(1)->value => $faces,
            CardType::patrol(2)->value => $faces,
            CardType::patrol(3)->value => $faces,
        );
    }

    function moveCardsOutOfPlay(DeckType $deck, string $name) {
        $type = $deck->cardType();
        $type_arg = $this->getCardTypeForName($type, $name);
        $oop = $this->cards->getCardsOfType($type->value, $type_arg);
        $this->cards->moveCards(array_keys($oop), $deck->oopName());
    }

    function chooseStartingTile($tile_id) {
        $entrance = $this->tiles->getCard($tile_id);
        $floor = $this->tileFloor($entrance);
        if ($floor != 1) {
            throw new BgaUserException(clienttranslate("Starting tile must be on the first floor"));
        }
        if (TileType::of($entrance) === TileType::Shaft) {
            throw new BgaUserException(clienttranslate("You cannot start on the shaft, this room is empty"));
        }
        $this->performPeek($entrance['id'], 'effect');

        // Move first player token to entrance
        $this->initStateValue(GameStateValue::EntranceTile, $tile_id );
        // $hand = $this->tokens->getPlayerHand(self::getCurrentPlayerId());
        $hand = $this->tokens->getPlayerHand($this->getCurrentPlayerIdCustom());
        $current_player_token = array_shift($hand);
        $this->moveToken($current_player_token['id'], 'tile', $tile_id);
        $this->pickTokensForTile(TokenType::Stairs, $tile_id);

        $this->nextPatrol(1);

        // Save the first undo restore point
        $this->undoSavepoint();
        $this->setStateValue(GameStateValue::UndoAllowed, 1);

        $this->gamestate->nextState();
    }

    /**
     * Create and shuffle one deck in the `cards` Deck component per card type.
     *
     * card_type: a CardType constant — 0 character, 1 tool, 2 loot, 3 event, 4-6 patrol deck for floor 1-3.
     * card_type_arg: which card of that type — its 1-based key in the type's material list
     * ($card_info[$type], or patrolNames() for patrol types). For patrol cards, type_arg - 1 is
     * therefore the board position (tile location_arg) named on the card.
     *
     * @param array<int, DeckType> $types
     * @param array<int, list<CardFace>> $info
     */
    function createDecks(array $types, array $info): void {
        foreach ( $types as $type => $desc ) {
            $cards = array ();
            foreach ( $info[$type] as $index => $value ) {
                $cards [] = array('type' => $type, 'type_arg' => $index + 1, 'nbr' => $value->nbr);
            }
            $deck_name = $desc->deckName();
            $this->cards->createCards( $cards, $deck_name );
            $this->cards->shuffle($deck_name);
        }
    }

    function gatherCardData($prefix, $types, $info) {
        $result = array();
        $result[$prefix.'_types'] = array();
        foreach ( $types as $type => $desc ) {
            $card_info = array();
            foreach ($info[$type] as $index => $value) {
                $card_info [] = array('type' => $type, 'index' => $index + 1, 'name' => $value->name);
            }

            $deck_name = $desc->deckName();
            $result[$deck_name] = $this->cards->getCardsInLocation( $deck_name );
            $result[$prefix.'_types'][$type] = array('name' => $desc->value, 'deck' => $deck_name, 'cards' => $card_info);
            $discard_name = $desc->discardName();
            $result[$discard_name] = $this->cards->getCardsInLocation( $discard_name );
            $result[$discard_name.'_top'] = $this->cards->getCardOnTop( $discard_name );
        }
        return $result;
    }

    function getPeekableTiles($player_tile, $variant='peek') {
        $peekable = array();
        $walls = $this->getWalls();
        $max_floor = $this->getFloorCount();
        for ($floor=1; $floor <= $max_floor; $floor++) { 
            $tiles = $this->getTiles($floor);
            foreach ($tiles as $tile) {
                if($tile['id'] != $player_tile['id'] && TileType::of($tile) === TileType::Back && $this->isTileAdjacent($tile, $player_tile, $walls, $variant)) {
                    $peekable [] = $tile;
                }
            }
        }
        return $peekable;
    }

    function openSafes() {
        $safes = $this->tiles->getCardsOfType(TileType::Safe->value);
        $open = 0;
        foreach ($safes as $tile_id => $tile) {
            if ($tile['location'] == 'oop')
                continue;
            if ($this->tokensInTile(TokenType::Open, $tile_id) == 0) {
                break;
            } else {
                $open++;
            }
        }
        return $open;
    }

    function canEscape($player_tile) {
        $thermal_to_roof = FALSE;
        $safes_needed = $this->scenario() === Scenario::OfficeJob ? 2 : 3;
        $max_floor = $this->getFloorCount();
        if ($this->tileFloor($player_tile) == $max_floor && $this->tokensInTile(TokenType::Thermal, $player_tile['id'])) {
            $tile_below = $this->findTileOnFloor($max_floor - 1, $player_tile['location_arg']);
            $thermal_to_roof = $this->tokensInTile(TokenType::Thermal, $tile_below['id']) == null;
        }
        return (TileType::of($player_tile) === TileType::Stairs || $thermal_to_roof) &&
            $this->tileFloor($player_tile) == $max_floor && $this->openSafes() == $safes_needed;
    }

    function canTrade($player_tile, $players_on_tile) {
        if (count($players_on_tile) == 1) {
            return FALSE;
        } else {
            $players_ids = array_column($players_on_tile, 'type_arg');
            $players_array = implode("','", $players_ids);
            // Check that any player has some tools or loot in their hand
            $tool = CardType::Tool->value;
            $loot = CardType::Loot->value;
            $sql = "SELECT card_id FROM card WHERE card_type IN ('$tool','$loot') AND card_location='hand' AND card_location_arg IN ('$players_array')";
            $result = self::getCollectionFromDB( $sql );
            return count($result) > 0;
        }
    }

    function gatherCurrentData($current_player_id) {
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        $players_on_tile = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $player_tile['id']);
        $character = $this->getPlayerCharacter($current_player_id);
        $character['name'] = $this->getCardType($character);
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining); 
        // $actions_description = $actions_remaining > 0 ?
        //     clienttranslate("${actions_remaining} actions") :
        //     '';
        return array(
            'escape' => $this->canEscape($player_tile),
            'peekable' => $this->getPeekableTiles($player_tile),
            'tradable' => $this->canTrade($player_tile, $players_on_tile),
            'player_token' => $player_token,
            'other_players' => count($players_on_tile) - 1,
            'character' => $character,
            'character_action_enabled' => $this->characterActionEnabled($current_player_id, $character),
            'tile' => $player_tile,
            'tile_tokens' => $this->tokens->getCardsInLocation('tile', $player_tile['id']),
            'tile_cards' => $this->cards->getCardsInLocation('tile', $player_tile['id']),
            'floor' => $this->tileFloor($player_tile),
            'actions_remaining' => $actions_remaining,
            // 'actions_description' => $actions_description,
            'undo_allowed' => $this->stateValue(GameStateValue::UndoAllowed),
            'kitty_escaped' => $this->isKittyEscaped(),
        );
    }

    function getCardType($card) {
        $info = $this->card_info[$card['type']];
        return $info[$card['type_arg'] - 1]->name;
    }
    function getCardTitle($card) {
        $info = $this->card_info[$card['type']];
        return $info[$card['type_arg'] - 1]->title;
    }
    function getCardTooltip($card) {
        $info = $this->card_info[$card['type']];
        $tooltip = $info[$card['type_arg'] - 1]->tooltip;
        return rtrim($tooltip,'.');
    }
    function getDisplayedCardName($card_name) {
        // Remove last character if 1, replace last char if 2 by Advanced and replace '-'' by space
        if (substr($card_name, -1) == '1') {
            $card_name_displayed = substr($card_name, 0, -1);
        } elseif (substr($card_name, -1) == '2') {
            $card_name_displayed = substr($card_name, 0, -1).clienttranslate(" Advanced");
        } else {
            $card_name_displayed = str_replace('-', ' ', $card_name);
        }
        return ucfirst($card_name_displayed);
    }
    function getCardChoiceDescription($card) {
        $info = $this->card_info[$card['type']];
        return $info[$card['type_arg'] - 1]->choice_description;
    }

    function getWalls() {
        return self::getObjectListFromDB("SELECT * from wall");
    }

    function getFlippedTiles($floor = null) {
        if ($floor) {
            return self::getCollectionFromDB("SELECT card_id id, safe_die FROM tile WHERE card_location='floor$floor' and flipped=1", true);       
        } else {
            return self::getCollectionFromDB("SELECT card_id id, card_type type, card_location location, card_location_arg location_arg FROM tile WHERE flipped=1");
        }
    }

    function getTiles($floor) {
        $tiles = $this->tiles->getCardsInLocation("floor$floor", null, 'location_arg');
        $flipped = $this->getFlippedTiles($floor);
        $stateName = $this->gamestate->getCurrentMainState()->name;
        if ($stateName !== 'gameEnd') {
            foreach ($tiles as &$tile) {
                if (!isset($flipped[$tile['id']])) {
                    $tile['type'] = TileType::Back->value; // face-down
                    $tile['type_arg'] = 0;
                    $tile['safe_die'] = 0;
                } else {
                    $tile['safe_die'] = $flipped[$tile['id']];
                }
            }
        }
        return $tiles;
    }

    function getTile($tile_id) {
        return self::getObjectFromDB("SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg, flipped, safe_die FROM tile WHERE card_id='$tile_id'");
    }

    // Typed floor number of a tile row; throws if the tile is not on a floor.
    function tileFloor($tile): int {
        return BurgleBrosTilePosition::fromRow($tile)->floor;
    }

    function getSafeDie($tile_id) {
        // Get Safe die count
        $sql = "SELECT card_id, safe_die FROM tile WHERE card_id=$tile_id";
        $result = self::getObjectFromDB($sql);
        if ($result && count($result) > 0) {
            return $result['safe_die'];
        } else {
            return 0;
        }
    }

    function setSafeDie($die_value, $tile_id, $floor = null) {
        return self::DbQuery("UPDATE tile SET safe_die=$die_value WHERE card_id=$tile_id");
    }

    function getSafeToken($tile_id) {
        // Get Safe crack token
        $sql = "SELECT card_id FROM token WHERE card_type='crack' AND card_location='tile' AND card_location_arg=$tile_id";
        $result = self::getObjectFromDB($sql);
        if ($result && count($result) > 0) {
            return $result['card_id'];
        } else {
            return 0;
        }
    }

    function getFloorAlarmTiles($floor) {
        $tile_location = "'floor$floor'";
        $sql = <<<SQL
            SELECT distinct tile.card_id id, tile.card_type type, tile.card_type_arg type_arg, tile.card_location location, tile.card_location_arg location_arg
            FROM token
            INNER JOIN tile ON token.card_location = 'tile' AND tile.card_id = token.card_location_arg
            WHERE token.card_type = 'alarm' AND tile.card_location = $tile_location
SQL;
        return self::getObjectListFromDB($sql);
    }

    function getFloorClosestAlarmTiles($floor) {
        // Return all the closest alarms of the guard on the chosen floor
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
        $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
        $alarm_tiles = $this->getFloorAlarmTiles($floor);
        if (count($alarm_tiles) > 1) {
            $paths = [];
            $shortest_path_length = PHP_INT_MAX;
            foreach ($alarm_tiles as $alarm_tile) {
                $path = $this->findShortestPathClockwise($floor, $guard_tile['location_arg'], $alarm_tile['location_arg']);
                $paths[] = $path;
                $shortest_path_length = min($shortest_path_length, count($path));
            }
            // Keep only the shortest paths
            $paths = array_filter($paths, function($path) use($shortest_path_length) { 
                return count($path) == $shortest_path_length;
            });
            return array_map(function($path) {
                return end($path);
            }, $paths);
        } elseif (count($alarm_tiles) == 1) {

            return [$alarm_tiles[0]];
        } else {
            return [];
        }
    }

    function nextPatrol($floor, $force=FALSE) {
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
        $alarm_tiles = $this->getFloorAlarmTiles($floor);
        $has_alarms = count($alarm_tiles) > 0;
        $draw_patrol = !$has_alarms || $force;
        $special_choice = FALSE;
        
        if ($draw_patrol) {
            // Cannot restart if new Patrol card is drawn or a new floor Guard position is revealed
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $patrol = DeckType::patrol($floor);
            do {
                $count = $this->cards->countCardInLocation($patrol->deckName());
                if ($count == 0) {
                    // Out of play
                    $this->cards->moveAllCardsInLocation($patrol->oopName(), $patrol->deckName());
                    $this->cards->moveAllCardsInLocation($patrol->discardName(), $patrol->deckName());
                    $this->cards->shuffle($patrol->deckName());
                    $count = 16;
                    $to_remove = $this->removePatrolPerPlayerCount($patrol);
                    $count -= $to_remove;
                    $die_count = $this->stateValue(GameStateValue::patrolDieCount($floor));
                    if ($die_count < 6) {
                        $next_count = $die_count + 1;
                        $this->setStateValue(GameStateValue::patrolDieCount($floor), $die_count + 1);
                        $this->bga->notify->all('message', clienttranslate('The Patrol Deck ran out of cards. The Guard on floor ${floor} now moves ${next_count} spaces'), [
                            'floor' => $floor,
                            'next_count' => $next_count,
                        ]);
                        $this->bga->notify->all('patrolDieIncreased', '', array(
                            'die_num' => $die_count + 1,
                            'token' => array_values($this->tokensOfType(TokenType::Patrol, $floor))[0],
                            'floor' => $floor
                        ));
                    }
                }
                $patrol_entrance = $this->cards->pickCardForLocation($patrol->deckName(), $patrol->discardName(), 16 - $count);
                $this->bga->notify->all('nextPatrol', '', array(
                    'floor' => $floor,
                    'cards' => $this->cards->getCardsInLocation($patrol->discardName()),
                    'top' => $patrol_entrance,
                    'deck_count' => $this->cards->countCardInLocation($patrol->deckName())
                ));
                $tile_id = $this->findTileOnFloor($floor, $patrol_entrance['type_arg'] - 1)['id'];
            } while($tile_id == $guard_token['location_arg']);
        }

        if ($has_alarms) {
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);

            // Get all the alarm tiles if any and find the closest (player may have to choose if more than 1)
            $alarm_tiles = $this->getFloorClosestAlarmTiles($floor);
            if (count($alarm_tiles) == 1) {
                $alarm_tile = reset($alarm_tiles);
                $tile_id = isset($alarm_tile['id']) ? $alarm_tile['id'] : $alarm_tile;
                // $tile_id = $alarm_tile['id'];
                // $tile_id = reset($alarm_tiles);
            } elseif (count($alarm_tiles) > 1 && !$force) {
                $tile_id = NULL;
                $special_choice = TRUE;
            }
        }
        if ($tile_id) {
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $this->moveToken($patrol_token['id'], 'tile', $tile_id, TRUE);
        }
        return $special_choice;
    }

    function removePatrolPerPlayerCount(DeckType $patrol) {
        $num_players = $this->getSoloMultiCharacters() > 1 ? $this->getSoloMultiCharacters() : self::getPlayersNumber();
        $to_remove = 0;
        if ($num_players == 1) {
            $to_remove = 9;
        } elseif ($num_players == 2) {
            $to_remove = 6;
        } elseif ($num_players == 3) {
            $to_remove = 3;
        }
        $this->cards->pickCardsForLocation($to_remove, $patrol->deckName(), $patrol->oopName());
        return $to_remove;
    }

    function setupPatrol($guard_token, $floor) {
        $patrol = DeckType::patrol($floor);
        $this->removePatrolPerPlayerCount($patrol);
        $guard_entrance = $this->cards->pickCardForLocation($patrol->deckName(), $patrol->discardName());
        $floor_tiles = $this->getTiles($floor);
        foreach ($floor_tiles as $tile) {
            if ($tile['location_arg'] == $guard_entrance['type_arg'] - 1) {
                $this->moveToken($guard_token['id'], 'tile', $tile['id'], TRUE);
                break;
            }   
        }
    }

    function findTileOnFloor($floor, $location_arg) {
        return array_values($this->tiles->getCardsInLocation("floor$floor", $location_arg))[0];
    }

    function flipTile($floor, $location_arg) {
        self::DbQuery("UPDATE tile SET flipped=1 WHERE card_location='floor$floor' and card_location_arg=$location_arg");
        $this->bga->notify->all('tileFlipped', '', array(
            'tile' => $this->findTileOnFloor($floor, $location_arg),
            'floor' => $floor,
            'undo_allowed' => $this->stateValue(GameStateValue::UndoAllowed),
            'flipped_tiles' => $this->getFlippedTiles(),
        ));
    }

    function endAction($action_cost = 1) {
        $actions_remaining = $this->incStateValue(GameStateValue::ActionsRemaining, -$action_cost);
        $this->setStateValue(GameStateValue::FirstAction, 0);
        // Force special choice reset to avoid guard infinite move
        $this->setStateValue(GameStateValue::SpecialChoiceArg, 0);
        // If irrerversible action, save a new restore point and change state of undo allowed to "last actions only"
        if ($this->stateValue(GameStateValue::UndoAllowed) == 0) {
            $this->undoSavepoint();
            $this->setStateValue(GameStateValue::UndoAllowed, 2);
        }
        $this->gamestate->nextState('endAction');
    }

    function resetGlobalVars() {
        $this->setStateValue(GameStateValue::InvisibleSuitActive, 0);
        $this->setStateValue(GameStateValue::CharacterAbilityUsed, 0);
        $this->setStateValue(GameStateValue::PlayerPass, 0);
        $this->setStateValue(GameStateValue::FirstAction, 1);
        $this->setStateValue(GameStateValue::AcrobatEnteredGuardTile, 0);
    }

    function tileAdjacencyDetail(BurgleBrosTilePosition $tile, BurgleBrosTilePosition $other_tile, $walls=null) {
        if (!isset($walls)) {
            $walls = $this->getWalls();
        }
        $plan = new BurgleBrosFloorPlan($this->getSquareSize(), $walls);
        return $plan->adjacencyDetail($tile, $other_tile);
    }

    function isTileAdjacent($tile, $other_tile, $walls=null, $variant='move', $throw_exception=TRUE) {
        $tile_pos = BurgleBrosTilePosition::fromRow($tile);
        $other_tile_pos = BurgleBrosTilePosition::fromRow($other_tile);
        $detail = $this->tileAdjacencyDetail($tile_pos, $other_tile_pos, $walls);

        $same_floor = $detail['same_floor'];
        $adjacent = $detail['adjacent'];
        $blocked = $detail['blocked'];
        
        if ($variant == 'guard') {
            return ($same_floor && $adjacent && !$blocked);
        } elseif($variant == 'acrobat1_enabled') {
            $flipped = $this->getFlippedTiles($tile_pos->floor);
            $secret_door = $same_floor && $adjacent && TileType::of($tile) === TileType::SecretDoor && isset($flipped[$tile['id']]);
            return ($same_floor && $adjacent && !$blocked) || 
                    $secret_door;
        } elseif($variant == 'peek') {
            return ($same_floor && $adjacent && !$blocked) ||
                $this->stairsAreAdjacent($tile, $other_tile, 'peek') ||
                $this->stairsAreAdjacent($other_tile, $tile, 'peek') ||
                $this->atriumIsAdjacent($tile, $other_tile) ||
                $this->thermalBombStairsAreAdjacent($tile, $other_tile, 'peek') ||
                $this->walkwayIsAdjacent($tile, $other_tile);
        } elseif($variant == 'peekhole') {
            return ($same_floor && $adjacent) || $this->peekholeIsAdjacent($tile, $other_tile);
        } elseif($variant == 'hawk1') {
            return $this->hawk1IsAdjacent($detail);
        } elseif($variant == 'acrobat2') {
            return $this->acrobat2IsAdjacent($tile, $other_tile) ||
                $this->acrobat2IsAdjacent($other_tile, $tile);
        } else {
            // $current_player_id = self::getCurrentPlayerId();
            $current_player_id = $this->getCurrentPlayerIdCustom();
            $painting = $this->getPlayerLoot('painting', $current_player_id);
            // Check $tile and $other_tile are different to avoid counting a move when clicking on service_duct
            // Check destination tile is flipped to avoid disclosing the other service duct card
            // Check tiles are not "standard" adjacent
            $flipped = $this->getFlippedTiles($tile_pos->floor);
            $service_duct = TileType::of($tile) === TileType::ServiceDuct && TileType::of($other_tile) === TileType::ServiceDuct && $tile['id'] != $other_tile['id'] && isset($flipped[$tile['id']]) && !($adjacent && !$blocked) ;
            $secret_door = $same_floor && $adjacent && TileType::of($tile) === TileType::SecretDoor && isset($flipped[$tile['id']]);
            if ($painting && !($variant == 'hawk2') && (($secret_door && $blocked) || $service_duct)) {
                if ($throw_exception) {
                    throw new BgaUserException(clienttranslate('Cannot move this way while holding the Painting'));
                } else {
                    return FALSE;
                }
            }
            return ($same_floor && $adjacent && !$blocked) ||
                $secret_door || $service_duct ||
                $this->stairsAreAdjacent($tile, $other_tile) ||
                $this->stairsAreAdjacent($other_tile, $tile) ||
                $this->thermalBombStairsAreAdjacent($tile, $other_tile) ||
                $this->walkwayIsAdjacent($tile, $other_tile);
        }
    }

    function stairsAreAdjacent($to, $from, $variant = 'move') {
        $time_lock = $this->getActiveEvent('time-lock');
        if ($time_lock && $variant != 'peek') {
            return FALSE;
        }
        return TileType::of($to) === TileType::Stairs &&
            $this->tileFloor($to) + 1 == $this->tileFloor($from) &&
            $to['location_arg'] == $from['location_arg'];
    }

    function atriumIsAdjacent($to, $from) {
        // Same tile position (location_arg) but one floor up or down
        return TileType::of($from) === TileType::Atrium &&
            $to['location_arg'] == $from['location_arg'] &&
            (abs($this->tileFloor($to) - $this->tileFloor($from)) == 1 || abs($this->tileFloor($to) - $this->tileFloor($from)) == 1);
    }

    function thermalBombStairsAreAdjacent($to, $from, $variant = 'move') {
        $time_lock = $this->getActiveEvent('time-lock');
        if ($time_lock && $variant != 'peek') {
            return FALSE;
        }
        return $this->tokensInTile(TokenType::Thermal, $to['id']) &&
            $this->tokensInTile(TokenType::Thermal, $from['id']) &&
            $from['location'] != $to['location'];
    }

    function walkwayIsAdjacent($to, $from) {
        $gymnastics_adjacent = FALSE;
        $gymnastics = $this->getActiveEvent('gymnastics');
        if ($gymnastics) {
            $gymnastics_adjacent = 
                (TileType::of($to) === TileType::Walkway &&
                    $this->tileFloor($to) + 1 == $this->tileFloor($from) &&
                    $to['location_arg'] == $from['location_arg']) ||
                (TileType::of($from) === TileType::Walkway &&
                    $this->tileFloor($from) + 1 == $this->tileFloor($to) &&
                    $from['location_arg'] == $to['location_arg']);
        }
        return $gymnastics_adjacent ||
            (TileType::of($from) === TileType::Walkway &&
                $this->tileFloor($from) - 1 == $this->tileFloor($to) &&
                $to['location_arg'] == $from['location_arg']);
    }

    function peekholeIsAdjacent($to, $from) {
        return $to['location_arg'] == $from['location_arg'] &&
            ($this->tileFloor($to) + 1 == $this->tileFloor($from) || $this->tileFloor($to) - 1 == $this->tileFloor($from));
    }

    function hawk1IsAdjacent($detail) {
        return $detail['same_floor'] && $detail['adjacent'] && $detail['blocked'] &&
            $this->getPlayerCharacter($this->getActivePlayerIdCustom(), 'hawk1') && 
            !$this->stateValue(GameStateValue::CharacterAbilityUsed);
    }

    function acrobat2IsAdjacent($to, $from) {
        return $this->tileFloor($to) + 1 == $this->tileFloor($from) &&
            $to['location_arg'] == $from['location_arg'];
    }

    function hawk2PeekAllowed($player_tile, $target_tile) {
        $walls = $this->getWalls();
        $adjacent_flipped = array();
        $face_down = false;
        $max_floor = $this->getFloorCount();
        for ($floor=1; $floor <= $max_floor; $floor++) { 
            $tiles = $this->getTiles($floor);
            foreach ($tiles as $tile) {
                if ($tile['id'] == $player_tile['id'] || (TileType::of($tile) !== TileType::Back && $this->isTileAdjacent($tile, $player_tile, $walls))) {
                    $adjacent_flipped [] = $tile;
                } else if ($tile['id'] == $target_tile['id'] && TileType::of($tile) === TileType::Back) {
                    $face_down = true;
                }
            }
        }
        if (!$face_down) {
            throw new BgaUserException(clienttranslate('Tile is already visible'));
        }
        foreach ($adjacent_flipped as $tile) {
            if ($this->isTileAdjacent($target_tile, $tile, $walls, 'hawk2')) {
                return true;
            }
        }
        throw new BgaUserException(clienttranslate('Tile is not valid for the Enhance ability'));
    }

    function moveGuardDebug($floor) {
        return $this->moveGuard(intval($floor), intval($floor) + 1);
    }

    function performGuardMovementEffects($guard_token, $tile_id, $create_path = FALSE) {
        $tile = $this->tiles->getCard($tile_id);
        $floor = $this->tileFloor($tile);
        $this->moveToken($guard_token['id'], 'tile', $tile_id, TRUE);
        if ($create_path) {
            $this->bga->notify->all('createGuardPath', '', array(
                'floor' => $floor,
                'path' => $this->getPathByLocation($floor, null)
            ));
        } else {
            $this->bga->notify->all('updateGuardPath', '', array(
                'floor' => $floor,
                'path' => $this->getPathByLocation($floor, null),
                'position' => $tile['location_arg']
            ));
        }
        $this->checkCameras(array('guard_id'=>$guard_token['id']));
        $this->handleGuardSeesPlayerTile($tile);
        $this->clearTileTokens(TokenType::Alarm, $tile_id);
    }

    function moveGuard($floor, $movement) {
        // Force movement reset after chosing the closest alarm
        $remaining_moves = $this->stateValue(GameStateValue::SpecialChoiceArg);
        if ($remaining_moves > 0) {
            $movement = $remaining_moves;
            $this->setStateValue(GameStateValue::SpecialChoiceArg, 0);
        }
        $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
        $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
        $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
        // If patrol token is on the same tile as the Guard (e.g. because of an event), activate next Patrol
        if ($guard_tile['id'] === $patrol_tile['id']) {
            $this->performGuardMovementEffects($guard_token, $guard_tile['id']);
            if ($this->nextPatrol($floor)) {        // alarm choice on multiple alarms
                $this->setStateValue(GameStateValue::SpecialChoiceArg, $movement);
                return TRUE;
            }
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);
        }

        $donut_type_id = $this->getCardTypeForName(CardType::Tool,'donuts');
        $donuts = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,$donut_type_id, 'tile', $guard_tile['id']);
        if (count($donuts) > 0 && $this->stateValue(GameStateValue::DonutsDropped) == 0) {
            $this->cards->moveCard(array_keys($donuts)[0], DeckType::Tools->discardName());
            $this->notifyTileCards($guard_tile['id']);
            return;
        }

        $path = $this->findShortestPathClockwise($floor, $guard_tile['location_arg'], $patrol_tile['location_arg']);
        foreach ($path as $tile_id) {
            if ($tile_id != $guard_token['location_arg']) {
                $movement--;
                if ($this->tokensInTile(TokenType::Crow, $tile_id) && $this->getPlayerCharacter(null, 'raven1')) {
                    $movement--;
                }
                $this->performGuardMovementEffects($guard_token, $tile_id);
                if ($this->stateValue(GameStateValue::StealthDepleted)) {
                    break;
                }
                if ($tile_id == $patrol_token['location_arg']) {
                    if ($this->nextPatrol($floor)) {        // tile choice on multiple alarms
                        $this->setStateValue(GameStateValue::SpecialChoiceArg, $movement);
                        return TRUE;
                    }
                    if ($movement > 0) {
                        return $this->moveGuard($floor, $movement);
                    }
                }
                if ($movement <= 0) {
                    break;
                }
            }
        }
    }

    function decrementPlayerStealth($player_id, $amount = 1) {
        // DEBUG on studio decrementPlayerStealth(2318199,-10)
        $table = $this->getSoloMultiCharacters() > 1 ? "solo_characters" : "player";
        self::DbQuery("UPDATE $table SET player_stealth_tokens = player_stealth_tokens - $amount WHERE player_id = '$player_id'");
        // $players = self::loadPlayersBasicInfos();
        $players = $this->loadPlayersInfos();
        $player_stealth = $this->tokensOfTypeInLocation(TokenType::Stealth, null, 'player', $player_id);
        $result['player_tokens'] = $this->tokensOfType(TokenType::Player);
        $token_id = key($this->tokensOfType(TokenType::Player, $player_id));
        if ($amount > 0) {
            if (count($player_stealth) > 0) {
                $this->moveToken(array_keys($player_stealth)[0], 'deck', TRUE);
            } else {
                $this->setStateValue(GameStateValue::StealthDepleted, 1);
            }
            $action = 'decrementStealth';
        } else if($amount < 0) {
            $this->pickTokens(TokenType::Stealth, 'player', $player_id, -$amount);
            $action = 'message';
        }
        $this->bga->notify->all($action, clienttranslate( '${player_name} ${action} one stealth' ), array(
            'i18n' => ['action'],
            'action' => $amount < 0 ? clienttranslate('gains') : clienttranslate('loses'),
            'player_name' => $players[$player_id]['player_name'],
            'meeple_id' => $token_id
        ));
    }

    function deductTileStealth($tile_id, $context, $player_id = null) {
        $player_tokens = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $tile_id);
        $tile_stealth = $this->tokensOfTypeInLocation(TokenType::Stealth, null, 'tile', $tile_id);
        $current_player_id = $player_id != null ? $player_id : self::getCurrentPlayerIdCustom();
        foreach ($player_tokens as $token) {
            if (count($tile_stealth) > 0) {
                // TODO: pick which players
                $stealth_token = array_shift($tile_stealth);
                $this->moveToken($stealth_token['id'], 'deck');
                // Use only one stealth if this is a player move
                if ($context == 'player')
                    return;
            } else if($context == 'guard' || $token['type_arg'] == $current_player_id) {
                $this->decrementPlayerStealth($token['type_arg']);
            }
        }
    }

    function getPlayerStealth($player_id) {
        if ($this->getSoloMultiCharacters() > 1) {
            $players = $this->loadPlayersInfos();
            return $players[$player_id]['stealth_tokens'];
        } else {
            return self::getUniqueValueFromDB("SELECT player_stealth_tokens FROM player WHERE player_id = '$player_id'");
        }
    }

    function atriumGuardsDebug($tile_id) {
        var_dump($this->atriumGuards($this->tiles->getCard($tile_id)));
    }

    function atriumGuards($tile) {
        $player_floor = $this->tileFloor($tile);
        $player_lower_floor = 'floor'.($player_floor - 1);
        $player_upper_floor = 'floor'.($player_floor + 1);
        $player_location_arg = $tile['location_arg'];
        $sql = <<<SQL
            SELECT count(*) > 0 as seen
            FROM tile
            INNER JOIN token ON token.card_location_arg = tile.card_id
            WHERE (tile.card_location = '$player_lower_floor' OR tile.card_location = '$player_upper_floor')
                AND tile.card_location_arg = '$player_location_arg'
                AND token.card_location = 'tile'
                AND token.card_type = 'guard'
SQL;
        return self::getUniqueValueFromDB($sql);
    }

    function handlePlayerEnteredGuardSight($tile, $player_id = null) {
        $invisible_suit = $this->stateValue(GameStateValue::InvisibleSuitActive) == 1;
        if ($invisible_suit) {
            return;
        }

        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $this->tileFloor($tile)))[0];
        $guard_tile = $this->tiles->getCard($guard_token['location_arg']);

        $current_player_id = $player_id != null ? $player_id : self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        
        $is_guard_tile = $tile['id'] == $guard_token['location_arg'];
        $is_player_tile = $tile['id'] == $player_token['location_arg'];
        
        if ($is_guard_tile && $is_player_tile) {
            $this->deductTileStealth($player_tile['id'], 'player', $current_player_id);
            return;
        }

        $tiara = $this->getPlayerLoot('tiara', $current_player_id);
        $is_adjacent = $this->isTileAdjacent($player_tile, $guard_tile, null, 'guard');
        $is_foyer = $is_adjacent &&
            (($is_guard_tile && TileType::of($player_tile) === TileType::Foyer) ||
                ($is_player_tile && (TileType::of($player_tile) === TileType::Foyer || $tiara)));
        if ($is_foyer) {
            $this->deductTileStealth($player_tile['id'], 'player');
            return;
        }
        if (TileType::of($player_tile) === TileType::Atrium && $is_player_tile && $this->atriumGuards($player_tile)) {
            $this->deductTileStealth($player_tile['id'], 'player');
            return;
        }
    }

    function handleGuardSeesPlayerTile($tile) {
        $this->deductTileStealth($tile['id'], 'guard');
        $guard_floor = $this->tileFloor($tile);

        $player_tokens = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile');
        foreach ($player_tokens as $token_id => $player_token) {
            $player_tile = $this->getPlayerTile($player_token['type_arg'], $player_token);

            $is_adjacent = $this->isTileAdjacent($tile, $player_tile, null, 'guard');
            $is_foyer = $is_adjacent && TileType::of($player_tile) === TileType::Foyer;
            if ($is_foyer) {
                $this->deductTileStealth($player_tile['id'], 'guard');
            }

            // TODO: Double check Atrium won't deduct twice if guard is also there
            // Deduct stealth if player is on Atrium the floor below or above
            $player_floor = $this->tileFloor($player_tile);
            if (TileType::of($player_tile) === TileType::Atrium && $tile['location_arg'] == $player_tile['location_arg'] 
                && abs($player_floor - $guard_floor) == 1) {
                $this->deductTileStealth($player_tile['id'], 'guard');
            }
        }
    }

    function findShortestPathDebug($floor, $start, $end) {
        // Inputs $floor as int (1,2,3), $start and $end are tiles id
        var_dump($this->findShortestPathClockwise(intval($floor),intval($start),intval($end)));
    }

    function lowestIn($values, $container) {
        asort($values);
        foreach ($values as $key => $value) {
            if (isset($container[$key])) {
                return $container[$key];
            }
        }
        throw new BgaUserException("Shouldn't get here");
    }

    function reconstructPath($came_from, $current) {
        $path = array($current);
        while(isset($came_from[$current])) {
            $current = $came_from[$current];
            array_unshift($path, $current);
        }
        return $path;
    }

    function findShortestPath($floor, $start, $end) {
        $tiles = array_values($this->tiles->getCardsInLocation("floor$floor", null, 'location_arg'));
        $walls = $this->getWalls();
        $grid = new BurgleBrosGrid($this->getSquareSize());

        // An implementation of https://en.wikipedia.org/wiki/A*_search_algorithm
        // Returned path contains tile ids
        $open_set = array($start=>$start);
        $came_from = array();

        $g_score = array($start=>0);
        $f_score = array($start=>$grid->manhattanDistance($start, $end));
        $iterations = 0;
        while (count($open_set) > 0) {
            $current = $this->lowestIn($f_score, $open_set);
            $current_tile = $tiles[$current];
            if ($current == $end) {
                return $this->reconstructPath($came_from, $current_tile['id']);
            }
            // var_dump($current);
            
            unset($open_set[$current]);
            
            $neighbors = array_filter($tiles, function($tile) use ($current_tile,$walls) {
                return $this->isTileAdjacent($tile, $current_tile, $walls, 'guard');
            });
            foreach ($neighbors as $id => $neighbor) {
                $index = intval($neighbor['location_arg']);
                $g = $g_score[$current] + 1;
                if (!isset($g_score[$index]) || $g < $g_score[$index]) {
                    $came_from[$neighbor['id']] = $current_tile['id'];
                    $g_score[$index] = $g;
                    $f_score[$index] = $g + $grid->manhattanDistance($current, $index);
                    if (!isset($open_set[$index])) {
                        $open_set[$index] = $index;
                    }
                }
            }
            $iterations++;
            // if ($iterations > 10) {
            //     break;
            // }
            // var_dump(array('os'=>$open_set,'fs'=>$f_score,'gs'=>$g_score,'cf'=>$came_from));
        }
    }

    function getPathByLocation($floor, $tiles = null) {
        // Return a path as an array of location_arg positions of tiles (top left is 0, bottom right is 15)
        $tiles = $tiles ? $tiles : self::getCollectionFromDB("SELECT card_id id, card_location_arg location_arg FROM tile");
        $path = $this->getGuardPath($floor);
        if ($path) {
            $path = array_map( function($tile_id) use($tiles) {
                return $tiles[$tile_id]['location_arg'];
            }, $path);
        }
        return $path;
    }


    function getGuardPath($floor) {
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
        $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
        $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
        if ($patrol_token['location'] == 'deck') {
            $patrol_tile = $guard_tile;
        } else {
            $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);
        }
        return $this->findShortestPathClockwise($floor, $guard_tile['location_arg'], $patrol_tile['location_arg']);
    }

    function findShortestPathClockwise($floor, $start, $end) {
        // Shortest guard path between the cells $start and $end, ties broken
        // most-clockwise; returns tile ids, both endpoints included.
        $tiles = array_values($this->tiles->getCardsInLocation("floor$floor", null, 'location_arg'));
        $plan = new BurgleBrosFloorPlan($this->getSquareSize(), $this->getWalls());
        $finder = new BurgleBrosPathfinder($plan, intval($floor));
        return array_map(function($cell) use ($tiles) {
            return $tiles[$cell]['id'];
        }, $finder->shortestPathClockwise(intval($start), intval($end)));
    }

    function getGenericTokens() {
        $types = implode("','", array_map(function($type) {
            return $type['name'];
        }, $this->token_types));
        $tokens = self::getCollectionFromDB("SELECT card_id id, card_type type, card_location location, card_location_arg location_arg FROM token WHERE card_location != 'deck' and card_type in ('$types')");
        foreach ($tokens as &$token) {
            $token['letter'] = strtoupper($token['type'][0]);
            // $token['color'] = $this->token_colors[$token['type']];
        }
        return $tokens;
    }

    /** @param TokenType[] $types */
    function getPlacedTokens(array $types, $location='tile') {
        $types_arg = "('".implode("','", array_map(fn($t) => $t->value, $types))."')";
        $rows = self::getObjectListFromDB("SELECT card_location_arg id, card_id token_id FROM token WHERE card_type in $types_arg AND card_location = '$location'");
        $result = array();
        foreach ($rows as $row) {
            if (!isset($result[$row['id']])) {
                $result[$row['id']] = array();
            }
            $result[$row['id']] [] = $row['token_id'];
        }
        return $result;
    }

    function notifyPlayerHand($player_id, $discard_ids=array()) {
        $this->bga->notify->all('playerHand', '', array(
            'player_id' => $player_id,
            'hand' => $this->cards->getPlayerHand($player_id),
            'discard_ids' => $discard_ids
        ));
    }

    function notifyTileCards($tile_id) {
        $this->bga->notify->all('tileCards', '', array(
            'tile_id' => $tile_id,
            'tokens' => $this->getCardTokens($tile_id)
        ));
    }

    function performSafeDiceRollDebug($floor, $dice_count) {
        $safe_tile = array_values($this->tiles->getCardsOfTypeInLocation(TileType::Safe->value, null, "floor$floor"))[0];
        $this->performSafeDiceRoll($safe_tile,intval($dice_count));
    }

    function performSafeDiceRoll($safe_tile, $drop_loot=0) {
        if (TileType::of($safe_tile) !== TileType::Safe) {
            throw new BgaUserException(clienttranslate("Tile is not a safe"));
        }
        if ($this->tokensInTile(TokenType::Open, $safe_tile['id'])) {
            throw new BgaUserException(clienttranslate("Safe is already open"));
        }

        $dice_count = $this->getSafeDie($safe_tile['id']);
        $current_player_id = $this->getCurrentPlayerIdCustom();
        if ($this->getPlayerCharacter($current_player_id, 'peterman1')) {
            $dice_count++;
        }
        if ($dice_count == 0) {
            throw new BgaUserException(clienttranslate("You have not added any dice"));
        }

        // Check player holding the keycard is rolling
        $keycard = $this->getPlayerLoot('keycard');
        if ($keycard) {
            $keycard_holder = $this->getPlayerToken($keycard['location_arg']);
            // This is advanced Peterman ($drop_loot === TRUE), check he has the keycard
            if ($keycard_holder['location_arg'] != $safe_tile['id']) {
                if ( $drop_loot ) {
                    if (!$this->getPlayerCharacter($keycard_holder['type_arg'], 'peterman2'))
                        throw new BgaUserException(clienttranslate("The player holding the keycard must be present"));
                } else {
                    throw new BgaUserException(clienttranslate("The player holding the keycard must be present"));        
                }
            }
        }
        $rolls = $this->rollDice($dice_count);
        $this->notifyRoll($rolls, 'safe');

        // If player owns the stethoscope (and not the bust), can choose to reroll a die
        $stethoscope = $this->getPlayerTool('stethoscope', $current_player_id);
        $bust = $this->getPlayerLoot('bust', $current_player_id);
        if ($stethoscope && !$bust) {
            // Store die values to get them back on next state
            $sql_values = [];
            foreach ($rolls as $value => $nbr) {
                for ($i=1; $i <= $nbr; $i++) { 
                    $sql_values[] = "('die', $value, 'stethoscope', 0)";
                }
            }
            $sql = "INSERT INTO token (card_type, card_type_arg, card_location, card_location_arg) VALUES ".implode(', ', $sql_values);
            self::DbQuery($sql);
            $this->setStateValue(GameStateValue::CardChoice, $stethoscope['id']);
            $drop_loot = $drop_loot > 0 ? $safe_tile['id'] : 0;
            $this->setStateValue(GameStateValue::DropLoot, $drop_loot);
            $this->gamestate->nextState('cardChoice');
            return true;
        } else {
            $this->applyDieRoll($rolls, $safe_tile, $drop_loot);
        }
    }

    function applyDieRoll($rolls=null, $safe_tile=null, $drop_loot=0) {
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $size_sq = $this->getSquareSize();
        if ($rolls === null) {
            $tokens = $this->tokens->getCardsInLocation('stethoscope');
            $rolls = array();
            foreach ($tokens as $id => $token) {
                $value = $token['type_arg'];
                $rolls[$value] = isset($rolls[$value]) ? $rolls[$value] + 1 : 1;
            }
            self::DbQuery("DELETE FROM token WHERE card_location='stethoscope'");
        }
        if ($safe_tile === null) {
            $player_token = $this->getPlayerToken($current_player_id);
            $safe_tile = $this->getPlayerTile($current_player_id, $player_token);
        }
        if ($drop_loot === 0) {
            $drop_loot = $this->stateValue(GameStateValue::DropLoot);
        } else {
            $this->setStateValue(GameStateValue::DropLoot, $drop_loot);
        }
        $floor = $this->tileFloor($safe_tile);
        $tiles = $this->getTiles($floor);
        $placed_tokens = $this->getPlacedTokens(array(TokenType::Safe));
        $grid = new BurgleBrosGrid($size_sq);
        $safe_row = $grid->rowOf($safe_tile['location_arg']);
        $safe_col = $grid->colOf($safe_tile['location_arg']);
        $cracked_count = 0;
        foreach ($tiles as $tile) {
            $row = $grid->rowOf($tile['location_arg']);
            $col = $grid->colOf($tile['location_arg']);
            if (($row == $safe_row || $col == $safe_col)) {
                if (!isset($placed_tokens[$tile['id']])) {
                    if (TileType::of($tile) !== TileType::Safe && isset($rolls[intval($tile['safe_die'])])) {
                        $this->pickTokensForTile(TokenType::Safe, $tile['id']);
                        $cracked_count++;
                    } elseif (TileType::of($tile) === TileType::Shaft || TileType::of($tile) === TileType::Safe) {
                        $cracked_count++;
                    }
                } else {
                    $cracked_count++;
                }
            }
        }
        // Safe is open
        if ($cracked_count - 1 == ($size_sq - 1) * 2) { // remove a safe cracked count
            $this->pickTokensForTile(TokenType::Open, $safe_tile['id']);
            if ($drop_loot > 0) {
                $this->setStateValue(GameStateValue::DrawToolsPlayer, $safe_tile['id']);
                $loot = $this->cards->pickCardForLocation(DeckType::Loot->deckName(), 'tile', $safe_tile['id']);
                $this->notifyTileCards($safe_tile['id']);
            } else {
                $this->setStateValue(GameStateValue::DrawToolsPlayer, $current_player_id);
                $loot = $this->cards->pickCard(DeckType::Loot->deckName(), $current_player_id);
            }
            $type = $this->getCardType($loot);
            if ($type == 'cursed-goblet' && !$drop_loot) {
                $stealth = $this->getPlayerStealth($current_player_id);
                if ($stealth > 0) {
                    $this->decrementPlayerStealth($current_player_id);
                }
                // Do not lose stealth if it is the last one
            } else if($type == 'gold-bar') {
                $gold_type = $this->getCardTypeForName(CardType::Loot,'gold-bar');
                $other_gold = array_values($this->cards->getCardsOfTypeInLocation(CardType::Loot->value,$gold_type, DeckType::Loot->deckName()))[0];
                $this->cards->moveCard($other_gold['id'], 'tile', $safe_tile['id']);
                $this->notifyTileCards($safe_tile['id']);
            }
            $this->notifyPlayerHand($current_player_id);
            
            $this->bga->notify->all('message', clienttranslate('${player_name} cracked the safe on floor ${floor}'), [
                'player_name' => self::getCurrentPlayerName(),
                'floor' => $floor
            ]);

            // $safe_token = array_values($this->tokensOfType(TokenType::Crack, $floor))[0];
            $safe_token_id = $this->getSafeToken($safe_tile['id']);
            if ($safe_token_id > 0) {
                $this->moveToken($safe_token_id, 'deck');
            }
            for ($lower_floor=$floor; $lower_floor >= 1; $lower_floor--) { 
                $die_count = $this->stateValue(GameStateValue::patrolDieCount($lower_floor));
                if ($die_count < 6) {
                    $next_count = $die_count + 1;
                    $this->setStateValue(GameStateValue::patrolDieCount($lower_floor), $next_count);
                    $this->bga->notify->all('message', clienttranslate('Guard on floor ${lower_floor} now moves ${next_count} spaces'), [
                        'lower_floor' => $lower_floor,
                        'next_count' => $next_count,
                    ]);
                    $this->bga->notify->all('patrolDieIncreased', '', array(
                        'die_num' => $die_count + 1,
                        'token' => array_values($this->tokensOfType(TokenType::Patrol, $lower_floor))[0],
                        'floor' => $lower_floor
                    ));
                }
            }
            // Show back current cracked safe floor (because patrolDieIncreased will show the 1st floor)
            $this->bga->notify->all('showFloor', '', [
                'floor' => $this->tileFloor($safe_tile),
                'delay' => false,
            ]);
        }
    }

    function getTokens($ids) {
        $tokens = $this->tokens->getCards($ids);
        foreach ($tokens as $token_id => &$token) {
            if (isset($this->token_types[$token['type']])) {
                $token['letter'] = strtoupper($token['type'][0]);
                if ($token['location'] == 'tile') {
                    $token['floor'] = $this->tileFloor($this->tiles->getCard($token['location_arg']));
                }
            }
        }
        return $tokens;
    }

    function getTokensOnFloor(TokenType $type, $floor) {
        $type_value = $type->value;
        $sql = <<<SQL
            SELECT token.card_id as id
            FROM token
            INNER JOIN tile ON tile.card_id = token.card_location_arg
            WHERE token.card_location = 'tile' AND tile.card_location = 'floor$floor' AND token.card_type = '$type_value'
SQL;
        return self::getObjectListFromDB($sql, TRUE);
    }

    function moveTokens($ids, $location, $location_arg=0, $synchronous=FALSE) {
        $this->tokens->moveCards($ids, $location, $location_arg);
        $name = $synchronous ? 'tokensPickedSync' : 'tokensPicked';
        $this->bga->notify->all($name, '', array(
            'tokens' => $this->getTokens($ids)
        ));
    }

    function moveToken($id, $location, $location_arg=0, $synchronous=FALSE) {
        $this->moveTokens(array($id), $location, $location_arg, $synchronous);
        $token = $this->tokens->getCard($id);
        if (TokenType::of($token) === TokenType::Patrol) {
            $tile = $this->tiles->getCard($token['location_arg']);
            $floor = $this->tileFloor($tile);
            $this->bga->notify->all('createGuardPath', '', array(
                'floor' => $floor,
                'path' => $this->getPathByLocation($floor, null)
            ));
        }
    }

    function pickTokens(TokenType $type, $to_location='tile', $to_location_arg=null, $nbr = 1) {
        $token_ids = array_keys($this->tokensOfTypeInLocation($type, null, 'deck'));
        $ids = array();
        for ($i=0; $i < $nbr; $i++) { 
            $ids [] = $token_ids[$i];
        }
        $this->moveTokens($ids, $to_location, $to_location_arg);
    }

    function pickTokensForTile(TokenType $type, $tile_id, $nbr = 1) {
        $this->pickTokens($type, 'tile', $tile_id, $nbr);
    }

    function clearTileTokens(TokenType $type, $tile_id=null) {
        $tokens = $this->tokensOfTypeInLocation($type, null, 'tile', $tile_id);
        $ids = array();
        foreach ($tokens as $token) {
            $ids [] = $token['id'];
        }
        $this->moveTokens($ids, 'deck');
    }

    function rollDice($dice_count) {
        $this->setStateValue(GameStateValue::UndoAllowed, 0);
        $rolls = array();
        for ($i=0; $i < $dice_count; $i++) { 
            $result = bga_rand(1, 6);
            $rolls[$result] = isset($rolls[$result]) ? $rolls[$result] + 1 : 1;
        }
        return $rolls;
    }

    function notifyRoll($rolls, $for) {
        $roll_list = array();
        for ($i=1; $i <= 6; $i++) { 
            if (isset($rolls[$i])) {
                $count = $rolls[$i];
                while ($count > 0) {
                    $roll_list [] = $i;
                    $count--;
                }
            }
        }
        $this->bga->notify->all('diceRolled', clienttranslate( '${player_name} rolled ${roll} for ${for}' ), array(
            'i18n' => ['for'],
            'player_name' => $this->getActivePlayerNameCustom(),
            'roll' => implode(',', $roll_list),
            'rolls' => $roll_list,
            'for' => $for
        ));
    }

    function rollDebug($dice_count) {
        $this->notifyRoll($this->rollDice(intval($dice_count)), 'debug');
    }

    function attemptKeypadRoll($tile) {
        $open = $this->getPlacedTokens(array(TokenType::Open));
        if (isset($open[$tile['id']])) {
            return TRUE; // Skip
        }

        $previous = $this->getPlacedTokens(array(TokenType::Keypad));
        $count = isset($previous[$tile['id']]) ? count($previous[$tile['id']]) + 1 : 1;
        // if ($this->getPlayerCharacter(self::getCurrentPlayerId(), 'peterman1')) {
        if ($this->getPlayerCharacter($this->getCurrentPlayerIdCustom(), 'peterman1')) {
            $count++;
        }
        $rolls = $this->rollDice($count);
        $this->notifyRoll($rolls, 'keypad');
        if (isset($rolls[6])) {
            $this->pickTokensForTile(TokenType::Open, $tile['id']);
            if (isset($previous[$tile['id']])) {
                foreach ($previous[$tile['id']] as $token_id) {
                    $this->moveToken($token_id, 'deck');
                }
            }
            return TRUE;
        }

        if($this->stateValue(GameStateValue::ActionsRemaining) > 1) {
            $this->pickTokensForTile(TokenType::Keypad, $tile['id']);
        }
        return FALSE;
    }

    function tokensInTile(TokenType $type, $tile_id) {
        $tokens = $this->tokensOfTypeInLocation($type, null, 'tile', $tile_id);
        return count($tokens);
    }

    function canHack($tile) {
        $hacker2 = $this->getPlacedTokens(array(TokenType::Hack),'card');
        if (count($hacker2) > 0) {
            return TRUE;
        }

        $tokens = $this->getPlacedTokens(array(TokenType::Hack));
        if (count($tokens) == 0) {
            return FALSE;
        }
        $tiles = $this->tiles->getCardsOfType(TileType::of($tile)->computer()->value);
        $computer_tile = array_values($tiles)[0];
        return isset($tokens[$computer_tile['id']]);
    }

    function getGemstonePenalty($player_id, $player_tile, $is_moved=FALSE) {
        $gemstone = $this->getPlayerLoot('gemstone', $player_id);
        // Token is already moved, so there was another player there
        $more_than = $is_moved ? 1 : 0;
        if ($gemstone && $this->tokensInTile(TokenType::Player, $player_tile['id']) > $more_than) {
            return 1;
        }
        return 0;
    }

    function canUseExtraAction($player_id, $player_tile) {
        $action_penalty = $this->getGemstonePenalty($player_id, $player_tile, TRUE);
        return TileType::of($player_tile) === TileType::Laser && $this->stateValue(GameStateValue::ActionsRemaining) >= (2 + $action_penalty);
    }

    function hackerDoesNotTrigger($tile) {
        if (!in_array(TileType::of($tile), array(TileType::Fingerprint, TileType::Motion, TileType::Laser), true)) {
            return FALSE;
        }

        $type_arg = $this->getCardTypeForName(CardType::Character,'hacker1');
        $hackers = $this->cards->getCardsOfTypeInLocation(CardType::Character->value,$type_arg, 'hand');
        if (count($hackers) > 0) {
            $hacker = array_values($hackers)[0];
            // if ($hacker['location_arg'] == self::getCurrentPlayerId()) {
            if ($hacker['location_arg'] == $this->getCurrentPlayerIdCustom()) {
                return TRUE;
            }

            $hacker_token = $this->getPlayerToken($hacker['location_arg']);
            return $hacker_token['location_arg'] == $tile['id'];
        }
        return FALSE;
    }

    function hackOrTrigger($tile) {
        $tile_choice = FALSE;
        $special_choice = FALSE;
        if ($this->tokensInTile(TokenType::Guard, $tile['id']) || $this->tokensInTile(TokenType::Alarm, $tile['id']) || $this->hackerDoesNotTrigger($tile) || $this->stateValue(GameStateValue::EmpPlayer) != 0) {
            // $tile_choice = FALSE;
            // return FALSE;
        } else {
            if ($this->canHack($tile)) {
                $tile_choice = TRUE;
            } else {
                $special_choice = $this->triggerAlarm($tile, TRUE);
            }
        }
        return array(
            'tile_choice' => $tile_choice,
            'special_choice' => $special_choice,
        );
    }

    function handleTilePeek($tile) {
        $type = TileType::of($tile);
        if ($type === TileType::Stairs) {
            $floor = $this->tileFloor($tile);
            $max_floor = $this->getFloorCount();
            if ($floor < $max_floor) {
                $upper_tile = $this->findTileOnFloor($floor + 1, $tile['location_arg']);
                $this->pickTokensForTile(TokenType::Stairs, $upper_tile['id']);
            }
        } elseif ($type === TileType::Lavatory) {
            $this->pickTokensForTile(TokenType::Stealth, $tile['id'], 3);
        } elseif ($type === TileType::Laboratory) {
            $this->notifyTileCards($tile['id']);
        }
        $this->setStateValue(GameStateValue::UndoAllowed, 0);
    }

    function setTileBit(GameStateValue $state, $tile_id) {
        $tile_bit = 1 << self::getUniqueValueFromDB("SELECT safe_die FROM tile WHERE card_id = '$tile_id'");
        $tile_entered = $this->stateValue($state);
        $this->setStateValue($state, $tile_entered | $tile_bit);
        return ($tile_entered & $tile_bit) != 0x0;
    }

    function notifyMovement($player_id, $tile, $context='move') {
        if ($context == 'deadbolt') {
            $msg = clienttranslate('${player_name} stayed in tile ${tile_name} on floor ${floor} because they didn\'t have enough actions to enter the Deadbolt');
        } else if ($context == 'keypad') {
            $msg = clienttranslate('${player_name} stayed in tile ${tile_name} on floor ${floor} because they didn\'t roll a 6 to enter the Keypad');
        } else if ($context == 'walkway') {
            $msg = clienttranslate('${player_name} fell to tile ${tile_name} on floor ${floor} because they revealed a Walkway');
        } else {
            $msg = clienttranslate('${player_name} moves to ${tile_name} on floor ${floor}');
        }
        $patrol_names = $this->patrolNames();
        $players = $this->loadPlayersInfos();
        $this->bga->notify->all('message', $msg, [
            'player_name' => $players[$player_id]['player_name'],
            'tile_name' => $patrol_names[$tile['location_arg']]['name'],
            'floor' => $this->tileFloor($tile),
        ]);
    }

    function handleTileMovement($tile, $player_tile, $player_token, $guard_token, $flipped_this_turn, $context) {
        $id = $tile['id'];
        $type = TileType::of($tile);
        // $actions_remaining = !in_array($context, array('action', 'acrobat2')) ? 1 : $this->stateValue(GameStateValue::ActionsRemaining);
        $actions_remaining = !in_array($context, array('action')) ? 1 : $this->stateValue(GameStateValue::ActionsRemaining);
        $cancel_move = false;
        $motion_entered = false;
        $tile_choice = false;
        $tile_choice_id = $id;
        $special_choice = false;
        $player_id = $context == 'rook1' ? $this->stateValue(GameStateValue::SpecialChoiceArg) : $player_token['type_arg'];
        $crowbar = $this->tokensInTile(TokenType::Crowbar, $id);
        $rook1_action = $context == 'rook1';
        $floor = $this->tileFloor($tile);

        $action_penalty = $this->getGemstonePenalty($player_id, $tile);
        if ($action_penalty > 0 && $actions_remaining < 2) {
            throw new BgaUserException(clienttranslate('Entering a tile with another player costs an additional action with the Gemstone'));
        }

        if ($context == 'acrobat2') {
            // Taking Tim's answer boardgamegeek.com/thread/1487753/wall-climbing-acrobat-questions
            // Acrobat cannot enter Deadbolt nor use an extra action to disarm Laser
            $action_penalty += 4;
        }

        if ($type === TileType::Deadbolt) {
            if (!$crowbar && !$rook1_action) {
                $people = $this->getPlacedTokens(array(TokenType::Player, TokenType::Guard));
                if (!isset($people[$id]) || count($people[$id]) == 0) {
                    if ($actions_remaining < (3 + $action_penalty)) {
                        if ($flipped_this_turn) {
                            $cancel_move = true;
                            $this->notifyMovement($player_id, $player_tile, 'deadbolt');
                        } else {
                            throw new BgaUserException(clienttranslate('You do not have enough actions to enter the Deadbolt'));
                        }
                    } else {
                        $this->incStateValue(GameStateValue::ActionsRemaining, -2); // One is deducted already
                    }
                }
            }
        } elseif ($type === TileType::Keypad) {
            if (!$crowbar) {
                $cancel_move = !$this->attemptKeypadRoll($tile);
                if ($cancel_move) {
                    $this->notifyMovement($player_id, $player_tile, 'keypad');
                    if ($context == 'acrobat1') {
                        $action_penalty++; // count a move for attempting to open a keypad
                    }
                }
            }
        } elseif ($type === TileType::Fingerprint) {
            if (!$crowbar) {
                $this->setupGuardToken($guard_token, $floor);
                $result = $this->hackOrTrigger($tile);
                $tile_choice = $result['tile_choice'];
                $special_choice = $result['special_choice'];
            }
        } elseif ($type === TileType::Laser) {
            if (!$crowbar && !$rook1_action && !$this->getPlayerLoot('mirror', $player_id) && !$this->hackerDoesNotTrigger($tile) && $this->stateValue(GameStateValue::EmpPlayer) == 0) {
                $this->setupGuardToken($guard_token, $floor);
                if ($actions_remaining >= (2 + $action_penalty)) {
                    $tile_choice = TRUE;
                } else {
                    $result = $this->hackOrTrigger($tile);
                    $tile_choice = $result['tile_choice'];
                    $special_choice = $result['special_choice'];
                }
            }
        } elseif ($type === TileType::Motion) {
            if (!$crowbar) {
                if (TileType::of($player_tile) === TileType::Motion) { // exiting a motion tile
                    $motion_entered = $this->stateValue(GameStateValue::MotionTileEntered);
                }
                $this->setTileBit(GameStateValue::MotionTileEntered, $id);
            }
        } elseif ($type === TileType::Laboratory) {
            $prev_value = $this->setTileBit(GameStateValue::LaboratoryTileEntered, $id);
            if (!$prev_value) {
                $this->setStateValue(GameStateValue::DrawToolsPlayer, $player_id);
                $this->notifyTileCards($id);
            }
        } elseif ($type === TileType::Detector) {
            if (!$crowbar && $this->stateValue(GameStateValue::EmpPlayer) == 0) {
                $hand = $this->cards->getPlayerHand($player_id);
                foreach ($hand as $card_id => $card) {
                    if ($card['type'] == CardType::Tool->value || $card['type'] == CardType::Loot->value) {
                        $this->setupGuardToken($guard_token, $floor);
                        $special_choice = $this->triggerAlarm($tile);
                        break;
                    }
                }
            }
        } elseif ($type === TileType::Walkway && $flipped_this_turn) {
            // Fall down
            if ($floor > 1) {
                $lower_tile = $this->findTileOnFloor($floor - 1, $tile['location_arg']);
                $cancel_move = true;
                $this->performPeek($lower_tile['id'], 'effect');
                $this->moveToken($player_token['id'], 'tile', $lower_tile['id']);
                $this->handlePlayerEnteredGuardSight($lower_tile);
                $this->notifyMovement($player_id, $lower_tile, 'walkway');
            }
        } elseif ($type === TileType::Thermo && $this->getPlayerLoot('isotope', $player_id)) {
            if (!$crowbar && $this->stateValue(GameStateValue::EmpPlayer) == 0) {
                $this->setupGuardToken($guard_token, $floor);
                $this->triggerAlarm($tile);
            }
        }

        if (!$cancel_move) {
            // Guarantee this is set up if it wasn't already but refresh guard token if already drawn from deck
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $this->setupGuardToken($guard_token, $floor);

            // Handle exit
            if (TileType::of($player_tile) === TileType::Motion && !$rook1_action) {
                $exit_id = $player_tile['id'];
                $motion_bit = 1 << self::getUniqueValueFromDB("SELECT safe_die FROM tile WHERE card_id = '$exit_id'");
                $motion_entered = $motion_entered !== false ? $motion_entered : $this->stateValue(GameStateValue::MotionTileEntered);
                if ($motion_entered && $motion_bit) {
                    $result = $this->hackOrTrigger($player_tile);
                    $exiting_choice = $result['tile_choice'];
                    $special_choice = $result['special_choice'];
                    if ($tile_choice && $exiting_choice) {
                        $this->setStateValue(GameStateValue::MotionTileExitChoice, $tile_choice_id);
                    }
                    if ($exiting_choice) {
                        $tile_choice = $exiting_choice;
                        $tile_choice_id = $player_tile['id'];
                    }
                }
            }
        
            $this->moveToken($player_token['id'], 'tile', $id);
        }
        if ( (!$tile_choice && $action_penalty) || $context == 'acrobat2' ) {
            $this->incStateValue(GameStateValue::ActionsRemaining, -$action_penalty);
        }
        if (!$cancel_move) {
            $this->notifyMovement($player_id, $tile);
        }
        return array(
            'perform_move' => !$cancel_move,
            'tile_choice' => $tile_choice ? $tile_choice_id : FALSE,
            'special_choice' => $special_choice,
        );
    }

    function checkCameras($params) {
        $video_loop = $this->getActiveEvent('video-loop');
        if ($video_loop || $this->stateValue(GameStateValue::EmpPlayer) != 0) {
            return;
        }
        $player_clause = '';
        $guard_clause = '';
        if (isset($params['guard_id'])) {
            $guard_id = $params['guard_id'];
            $guard_clause = "AND token.card_id = $guard_id";
        } else {
            $player_id = $params['player_id'];
            $player_clause = "AND token.card_id = $player_id";
        }
        $sql = <<<SQL
            SELECT distinct tile.card_id id, tile.card_type type, tile.card_location location, tile.card_location_arg location_arg
            FROM tile
            INNER JOIN token ON token.card_location = 'tile' AND token.card_location_arg = tile.card_id
            WHERE tile.card_type = 'camera' AND token.card_type = 'player' $player_clause AND EXISTS (
                SELECT token.card_id
                FROM tile
                INNER JOIN token ON token.card_location = 'tile' AND token.card_location_arg = tile.card_id
                WHERE tile.card_type = 'camera' AND token.card_type = 'guard' $guard_clause and tile.flipped=1)
SQL;
        $camera_tiles = self::getObjectListFromDB($sql);
        foreach ($camera_tiles as $tile) {
            if (!$this->tokensInTile(TokenType::Crowbar, $tile['id'])) {
                $this->triggerAlarm($tile);
            }
        }
    }

    function triggerAlarm($tile, $skip_token_checks=FALSE, $skip_hacker_checks=FALSE) {
        if (!$skip_token_checks) {
            if ($this->tokensInTile(TokenType::Guard, $tile['id']) || $this->tokensInTile(TokenType::Alarm, $tile['id']) || $this->stateValue(GameStateValue::EmpPlayer) != 0 && (!$skip_hacker_checks && $this->hackerDoesNotTrigger($tile))) {
                return;
            }
        }

        $floor = $this->tileFloor($tile);
        $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
        $this->moveToken($patrol_token['id'], 'tile', $tile['id']);
        $this->pickTokensForTile(TokenType::Alarm, $tile['id']);
        $this->bga->notify->all('message', clienttranslate( 'An alarm was triggered' ), array());
        self::incStat(1, 'alarm_triggered');
        // Let player choose the closest alarm if relevant
        return $this->nextPatrol($floor);
    }

    function handleToolEffectDebug($name) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $type_arg = $this->getCardTypeForName(CardType::Tool,$name);
        $card = array_values($this->cards->getCardsOfType(CardType::Tool->value,$type_arg))[0];
        $choice = $this->handleToolEffect($current_player_id, $card);
        if ($choice) {
            $this->setStateValue(GameStateValue::CardChoice, $card['id']);
            $this->gamestate->nextState('cardChoice');
        }
    }

    function handleToolEffect($player_id, $card) {
        $type = $this->getCardType($card);
        $choice = FALSE;
        if ($type == 'emp') {
            $this->setStateValue(GameStateValue::EmpPlayer, $player_id);
            $this->clearTileTokens(TokenType::Alarm);
        } elseif($type == 'invisible-suit') {
            $this->setStateValue(GameStateValue::InvisibleSuitActive, 1);
            $this->incStateValue(GameStateValue::ActionsRemaining, 1);
        } elseif ($type == 'makeup-kit') {
            $tile = $this->getPlayerTile($player_id);
            $player_tokens = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $tile['id']);
            foreach ($player_tokens as $token) {
                $this->decrementPlayerStealth($token['type_arg'], -1); // Give them back one
            }
        } elseif($type == 'rollerskates') {
            $this->incStateValue(GameStateValue::ActionsRemaining, 2);
        } elseif ($type == 'smoke-bomb') {
            $tile = $this->getPlayerTile($player_id);
            $this->pickTokensForTile(TokenType::Stealth, $tile['id'], 3);
        } elseif ($type == 'stethoscope') {
            throw new BgaUserException(clienttranslate("You must roll dice before using the stethoscope"));
        } elseif ($type == 'crystal-ball') {
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $choice = TRUE;
        } else {
            $choice = TRUE;
        }
        return $choice;
    }

    function getCardTypeForName(CardType $type, string $name): ?int {
        $type_arg = null;
        foreach ($this->card_info[$type->value] as $index => $value) {
            if ($value->name == $name) {
                $type_arg = $index + 1;
            }
        }
        return $type_arg;
    }

    function drawToolDebug($name = null, $location='hand', $location_arg=null) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        if (is_null($location_arg)) {
            $location_arg = $current_player_id;
        }
        
        if ($name != null) {
            $type_arg = $this->getCardTypeForName(CardType::Tool,$name);
            $card = array_values($this->cards->getCardsOfType(CardType::Tool->value,$type_arg))[0];
            $this->cards->moveCard($card['id'], $location, $location_arg);
            if ($location == 'hand') {
                $this->notifyPlayerHand($current_player_id);
            } else if ($location == 'tile') {
                $this->notifyTileCards($location_arg);
            }
        } else {
            $this->setStateValue(GameStateValue::DrawToolsPlayer, $current_player_id);
            $this->gamestate->nextState('endAction');
        }
        
    }

    function drawLootDebug($name) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $type_arg = $this->getCardTypeForName(CardType::Loot,$name);
        $card = array_values($this->cards->getCardsOfType(CardType::Loot->value,$type_arg))[0];
        $this->cards->moveCard($card['id'], 'hand', $current_player_id);
        $this->notifyPlayerHand($current_player_id);
    }

    function discardLootDebug($name) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $type_arg = $this->getCardTypeForName(CardType::Loot,$name);
        $card = array_values($this->cards->getCardsOfType(CardType::Loot->value,$type_arg))[0];
        $this->cards->moveCard($card['id'], DeckType::Loot->deckName());
        $this->notifyPlayerHand($current_player_id, array($card['id']));
    }

    function drawCharacterDebug($name) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $current_char = $this->getPlayerCharacter($current_player_id);
        $this->cards->moveCard($current_char['id'], DeckType::Characters->deckName());

        $type_arg = $this->getCardTypeForName(CardType::Character,$name);
        $card = array_values($this->cards->getCardsOfType(CardType::Character->value,$type_arg))[0];
        $this->cards->moveCard($card['id'], 'hand', $current_player_id);
        $this->notifyPlayerHand($current_player_id, array($current_char['id']));
    }

    function addActionDebug() {
        $this->setStateValue(GameStateValue::ActionsRemaining, 10);
    }

    function handleEventEffectDebug($name) {
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $type_arg = $this->getCardTypeForName(CardType::Event,$name);
        $card = array_values($this->cards->getCardsOfType(CardType::Event->value,$type_arg))[0];
        $event_result = $this->handleEventEffect($current_player_id, $card);
        if ($event_result['card_choice']) {
            $this->setStateValue(GameStateValue::CardChoice, $card['id']);
            $this->gamestate->nextState('cardChoice');
        } elseif ($event_result['tile_choice']) {
            $this->gamestate->nextState('tileChoice');
        }
    }

    function handleEventEffect($player_id, $card) {
        $type = $this->getCardType($card);
        $card_choice = FALSE;
        $tile_choice = FALSE;
        $special_choice = FALSE;
        $player_choice = FALSE;
        $max_floor = $this->getFloorCount();
        if ($type == 'brown-out') {
            for ($floor=1; $floor <= $max_floor; $floor++) { 
                $token_ids = $this->getTokensOnFloor(TokenType::Alarm, $floor);
                $this->moveTokens($token_ids, 'deck');
                foreach ($token_ids as $id) {
                    $this->nextPatrol($floor, TRUE);
                }
            }
        } elseif ($type == 'buddy-system') {
            if (self::getPlayersNumber() - count($this->getEscapedPlayers()) > 1 || 
                $this->getSoloMultiCharacters() - count($this->getEscapedPlayers()) > 1) {
                $card_choice = TRUE;
            }
        } elseif ($type == 'change-of-plans') {
            $tile = $this->getPlayerTile($player_id);
            $floor = $this->tileFloor($tile);
            // Shouldn't activate if active alarms (https://boardgamegeek.com/thread/1552574/official-frequently-asked-questions)
            $alarm_tiles = $this->getFloorAlarmTiles($floor);
            if (count($alarm_tiles) == 0)
                $this->nextPatrol($floor, TRUE);
        } elseif ($type == 'crash') {
            $tile = $this->getPlayerTile($player_id);
            $floor = $this->tileFloor($tile);
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $this->moveToken($patrol_token['id'], 'tile', $tile['id']);
        } elseif($type == 'dead-drop') {
            $prev_player_id = self::getPlayerBeforeCustom($player_id);
            $cards = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,null, 'hand', $player_id) +
                $this->cards->getCardsOfTypeInLocation(CardType::Loot->value,null, 'hand', $player_id);
            $this->cards->moveCards(array_keys($cards), 'hand', $prev_player_id);
            $this->notifyPlayerHand($player_id, array_keys($cards));
            $this->notifyPlayerHand($prev_player_id);
        } elseif ($type == 'freight-elevator') {
            $player_token = $this->getPlayerToken($player_id);
            $tile = $this->getPlayerTile($player_id, $player_token);
            $floor = $this->tileFloor($tile);
            if ($floor < $max_floor) {
                $upper_tile = $this->findTileOnFloor($floor + 1, $tile['location_arg']);
                $this->performPeek($upper_tile['id'], 'effect');
                $this->moveToken($player_token['id'], 'tile', $upper_tile['id']);
                $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor + 1))[0];
                if ($guard_token['location'] == 'deck') {
                    $this->setupPatrol($guard_token, $floor + 1);
                    $special_choice = $this->nextPatrol($floor + 1);
                }
            }
        } elseif($type == 'go-with-your-gut') {
            $player_tile = $this->getPlayerTile($player_id);
            $peekable = $this->getPeekableTiles($player_tile);
            if (count($peekable) > 1) {
                $card_choice = TRUE;
            } elseif(count($peekable) == 1) {
                $tile_choice = $this->performMove($peekable[0]['id'], 'event')['tile_choice'];
            } 
        } elseif($type == 'heads-up') {
            $next_player = $this->getPlayerAfterCustom($player_id);
            $player_token = $this->getPlayerToken($next_player);
            $all_other_players_escaped = 0;
            $players_count = $this->getSoloMultiCharacters() > 1 ? $this->getSoloMultiCharacters() : self::getPlayersNumber();
            while ($player_token['location'] == 'roof') {
                $next_player = $this->getPlayerAfterCustom();
                if (++$all_other_players_escaped >= $players_count) {
                    $next_player = $player_id;
                }
                $player_token = $this->getPlayerToken($next_player);
            }
            $this->cards->moveCard($card['id'], 'hand', $next_player);
            $this->notifyPlayerHand($next_player);
        } elseif ($type == 'jury-rig') {
            $this->setStateValue(GameStateValue::DrawToolsPlayer, $player_id);
        } elseif ($type == 'keycode-change') {
            $safes = $this->tiles->getCardsOfType(TileType::Keypad->value);
            foreach ($safes as $tile_id => $safe) {
                $this->clearTileTokens(TokenType::Open, $tile_id);    
            }
        } elseif ($type == 'lampshade') {
            $player_token = $this->getPlayerToken($player_id);
            $this->decrementPlayerStealth($player_id, -1); // Give them back one
        } elseif($type == 'lost-grip') {
            $player_token = $this->getPlayerToken($player_id);
            $tile = $this->getPlayerTile($player_id, $player_token);
            $floor = $this->tileFloor($tile);
            if ($floor > 1) {
                $lower_tile = $this->findTileOnFloor($floor - 1, $tile['location_arg']);
                $this->performPeek($lower_tile['id'], 'effect');
                $this->moveToken($player_token['id'], 'tile', $lower_tile['id']);
            }
        } elseif($type == 'peekhole') {
            $player_tile = $this->getPlayerTile($player_id);
            $peekable = $this->getPeekableTiles($player_tile, 'peekhole');
            if (count($peekable) > 1) {
                $card_choice = TRUE;
            } elseif(count($peekable) == 1) {
                $this->performPeek($peekable[0]['id'], 'peekhole');
            } 
        } elseif ($type == 'reboot') {
            for ($floor=1; $floor <= $max_floor; $floor++) {
                $tiles = $this->getTiles($floor);
                foreach ($tiles as $tile_id => $tile) {
                    if (TileType::of($tile)->isComputer()) {
                        $hack_tokens = $this->tokensOfTypeInLocation(TokenType::Hack, null, 'tile', $tile['id']);
                        if (count($hack_tokens) == 0) {
                            $this->pickTokensForTile(TokenType::Hack, $tile['id']);
                        } else {
                            $count = count($hack_tokens);
                            while ($count > 1) {
                                $token = array_shift($hack_tokens);
                                $this->moveToken($token['id'], 'deck');
                                $count--;
                            }
                        }
                    }
                }
            }
        } elseif($type == 'shoplifting') {
            $laboratories = self::getCollectionFromDB("SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg, safe_die FROM tile WHERE card_type = 'laboratory'");
            $tile_entered = $this->stateValue(GameStateValue::LaboratoryTileEntered);
            foreach ($laboratories as $tile_id => $tile) {
                $tile_bit = 1 << $tile['safe_die'];
                if (($tile_entered & $tile_bit) != 0x0) {
                    $special_choice = $this->triggerAlarm($tile);
                }
            }
        } elseif ($type == 'switch-signs') {
            $player_token = $this->getPlayerToken($player_id);
            $tile = $this->getPlayerTile($player_id, $player_token);
            $floor = $this->tileFloor($tile);

            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $this->moveToken($guard_token['id'], 'tile', $patrol_token['location_arg']);
            $this->moveToken($patrol_token['id'], 'tile', $guard_token['location_arg']);
            // If there was donuts under the Guard, move it to the new destination
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            $donut_type_id = $this->getCardTypeForName(CardType::Tool,'donuts');
            $donuts = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,$donut_type_id, 'tile', $guard_tile['id']);
            if (count($donuts) > 0 && $this->stateValue(GameStateValue::DonutsDropped) == 0) {
                $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);
                $this->cards->moveCard(array_keys($donuts)[0], 'tile', $patrol_tile['id']);
                $this->notifyTileCards($guard_tile['id']);
                $this->notifyTileCards($patrol_tile['id']);
            }
            // Check if player on destination would lose stealth
        } elseif ($type == 'where-is-he') {
            $player_token = $this->getPlayerToken($player_id);
            $tile = $this->getPlayerTile($player_id, $player_token);
            $floor = $this->tileFloor($tile);
            
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            
            $this->performGuardMovementEffects($guard_token, $patrol_token['location_arg']);

            $donut_type_id = $this->getCardTypeForName(CardType::Tool,'donuts');
            $donuts = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,$donut_type_id, 'tile', $guard_tile['id']);
            if (count($donuts) > 0) {
                $this->cards->moveCard(array_keys($donuts)[0], DeckType::Tools->discardName());
                $this->notifyTileCards($guard_tile['id']);
                return;
            } else {
                $special_choice = $this->nextPatrol($floor);
            }
        } elseif ($type == 'squeak') {
            // Guard moves 1 tile towards the closest player
            $tile = $this->getPlayerTile($player_id);
            $floor = $this->tileFloor($tile);
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            $paths = [];
            $shortest_path_length = PHP_INT_MAX;

            // $players = self::loadPlayersBasicInfos();
            $players = $this->loadPlayersInfos();
            foreach ($players as $player_id => $player) {
                $player_token = $this->getPlayerToken($player_id);
                $player_tile = $this->getPlayerTile($player_id, $player_token);
                if ($this->tileFloor($player_tile) != $floor)
                    continue;
                $path = $this->findShortestPathClockwise($floor, $guard_tile['location_arg'], $player_tile['location_arg']);
                $paths[] = $path;
                $shortest_path_length = min($shortest_path_length, count($path));
            }
            // Keep only the shortest paths
            $paths = array_filter($paths, function($path) use($shortest_path_length) { 
                return count($path) == $shortest_path_length;
            });
            // If more than 1 player at the same distance, let player choose
            if (count($paths) == 1) {
                $path = reset($paths);
                // If Guard and player are on the same tile, do not move
                if (count($path) > 1) {
                    $tile_id = $path[1];
                    $this->performGuardMovementEffects($guard_token, $tile_id);
                    $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
                    if ($tile_id == $patrol_token['location_arg'])
                        $special_choice = $this->nextPatrol($floor);                    
                }
            } else {
                $player_choice = 4;
                $this->setStateValue(GameStateValue::PlayerChoiceArg, $shortest_path_length);
            }
        } elseif ($type == 'throw-voice') {
            $card_choice = TRUE;
        } else {
            // It will be handled in the appropriate place
            $this->cards->moveCard($card['id'], 'hand', $player_id);
            $this->notifyPlayerHand($player_id);
        }
        return array(
            'card_choice' => $card_choice,
            'tile_choice' => $tile_choice,
            'special_choice' => $special_choice,
            'player_choice' => $player_choice
        );
    }

    function getActiveEvent($name) {
        $type_arg = $this->getCardTypeForName(CardType::Event,$name);
        $cards = $this->cards->getCardsOfTypeInLocation(CardType::Event->value,$type_arg, 'hand');
        if (count($cards) > 0) {
            return array_values($cards)[0];
        }
        return null;
    }

    function getPlayerLoot($name, $player_id=null) {
        $type_arg = $this->getCardTypeForName(CardType::Loot,$name);
        $cards = $this->cards->getCardsOfTypeInLocation(CardType::Loot->value,$type_arg, 'hand', $player_id);
        if (count($cards) > 0) {
            return array_values($cards)[0];
        }
        return null;
    }
    function getLootOwner($name) {
        $type_arg = $this->getCardTypeForName(CardType::Loot,$name);
        $cards = $this->cards->getCardsOfTypeInLocation(CardType::Loot->value,$type_arg, 'hand');
        if (count($cards) > 0) {
            return array_values($cards)[0];
        }
        return null;

    }

    function getPlayerTool($name, $player_id=null) {
        $type_arg = $this->getCardTypeForName(CardType::Tool,$name);
        $cards = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,$type_arg, 'hand', $player_id);
        if (count($cards) > 0) {
            return array_values($cards)[0];
        }
        return null;
    }

    function getPlayerCharacter($player_id, $name=null) {
        $type_arg = null;
        if($name != null) {
            $type_arg = $this->getCardTypeForName(CardType::Character,$name);
        }
        $cards = $this->cards->getCardsOfTypeInLocation(CardType::Character->value,$type_arg, 'hand', $player_id);
        return $cards ? array_values($cards)[0] : null;
    }

    function getPlayerToken($player_id) {
        return array_values($this->tokensOfType(TokenType::Player, $player_id))[0];
    }

    function getPlayerTile($player_id, $player_token=null) {
        if (!$player_token) {
            $player_token = $this->getPlayerToken($player_id);
        }
        return $this->tiles->getCard($player_token['location_arg']);
    }

    function getEscapedPlayers() {
        return array_column($this->tokensOfTypeInLocation(TokenType::Player, null, 'roof'), 'type_arg');
    }

    function validateSelection($expected_type, $selected_type) {
        if ($expected_type != $selected_type) {
            if ($expected_type == 'button') {
                throw new BgaUserException(clienttranslate("Finish first the action you started (use buttons in the status bar)"));
            } else {
                throw new BgaUserException(clienttranslate("Invalid selection. Expected: $expected_type."));
            }
        }
    }

    function handleSelectCardChoice($card, $selected_type, $selected_ids) {
        $selected_id = count($selected_ids) == 1 ? $selected_ids[0] : $selected_ids;
        $type = $card ? $this->getCardType($card) : null;
        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        if ($card && $card['type'] == CardType::Character->value) {
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        }
        $tile_choice = FALSE;
        $special_choice = FALSE;
        $discard = TRUE;
        if ($type == 'acrobat1') {
            $this->validateSelection('tile', $selected_type);
            // Don't do tile_choice here, since we'll never trigger an alarm
            $this->performMove($selected_id, 'acrobat1');
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } else if($type == 'acrobat2') {
            $this->validateSelection('button', $selected_type);
            $tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            $floor = $selected_id;
            $other_tile = $this->findTileOnFloor($floor, $tile['location_arg']);
            $result = $this->performMove($other_tile['id'], 'acrobat2');
            $tile_choice = $result['tile_choice'];
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } else if ($type == 'blueprints') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            $flipped = $this->getFlippedTiles($this->tileFloor($tile));
            if (isset($flipped[$selected_id])) {
                throw new BgaUserException(clienttranslate('Tile is already visible'));
            }
            $this->performPeek($tile['id'], 'effect');
        } elseif($type == 'buddy-system') {
            if ($selected_type == 'button') {
                $other_token = $this->tokens->getCard($selected_id);
            } else {
                $this->validateSelection('meeple', $selected_type);
                $other_token = $this->tokens->getCard($selected_id);
            }
            if (TokenType::of($other_token) !== TokenType::Player) {
                throw new BgaUserException(clienttranslate("Must choose a player token"));
            }
            if ($other_token['type_arg'] == $current_player_id) {
                throw new BgaUserException(clienttranslate('You cannot choose yourself'));
            }
            // $player_token = $this->getPlayerToken(self::getCurrentPlayerId());
            $player_token = $this->getPlayerToken($this->getCurrentPlayerIdCustom());
            $this->moveToken($other_token['id'], 'tile', $player_token['location_arg']);
        } elseif ($type == 'crowbar') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            // $player_tile = $this->getPlayerTile(self::getCurrentPlayerId());
            $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            if (!$this->isTileAdjacent($tile, $player_tile, null, 'peek')) {
                throw new BgaUserException(clienttranslate('Tile is not adjacent'));
            }
            // Check tile type is legitimate for crowbar
            $available_tiles = [TileType::Camera, TileType::Deadbolt, TileType::Detector, TileType::Fingerprint,
                TileType::Keypad, TileType::Laser, TileType::Motion, TileType::Thermo];
            if (!in_array(TileType::of($tile), $available_tiles, true)) {
                throw new BgaUserException( sprintf(clienttranslate("There is no point in using the crowbar on a %s tile, please try on another one"), $tile['type']) );
            }
            $this->pickTokensForTile(TokenType::Crowbar, $tile['id']);
        } elseif ($type == 'donuts') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            $guard_token = array_values($this->tokensOfTypeInLocation(TokenType::Guard, null, 'tile', $tile['id']));
            if (count($guard_token) == 0) {
                throw new BgaUserException(clienttranslate('Tile does not contain a guard'));
            }
            $this->cards->moveCard($card['id'], 'tile', $tile['id']);
            $this->notifyTileCards($tile['id']);
            $discard = FALSE;
        } elseif($type == 'dynamite') {
            $this->validateSelection('wall', $selected_type);
            $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            $floor = $this->tileFloor($player_tile);
            $grid = new BurgleBrosGrid($this->getSquareSize());

            $wall = self::getObjectFromDB("SELECT * FROM wall WHERE id = '$selected_id'");
            $cells = $grid->cellsSeparatedBy($wall);
            if ($this->scenario() === Scenario::FortKnox &&
                    in_array($this->board->getShaftPosition(), $cells, true)) {
                throw new BgaUserException(clienttranslate('You cannot blow up a wall of the pillar'));
            }
            if ($wall['floor'] != $floor || !in_array((int) $player_tile['location_arg'], $cells, true)) {
                throw new BgaUserException(clienttranslate('This wall is not adjacent'));
            }
            self::DbQuery("DELETE FROM wall WHERE id = '$selected_id'");
            $special_choice = $this->triggerAlarm($player_tile);
            // Force refresh of Guard path if there was already an alarm on the tile
            if (!$special_choice) {
                $special_choice = $this->nextPatrol($floor);
            }
            // Notify players to remove wall
            $this->bga->notify->all('removeWall', '', array(
                'wall_id' => $selected_id,
            ));
        } elseif($type == 'go-with-your-gut') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            $flipped = $this->getFlippedTiles($this->tileFloor($tile));
            if (isset($flipped[$tile['id']])) {
                throw new BgaUserException(clienttranslate('This tile is already visible'));
            }
            $result = $this->performMove($selected_id, 'event');
            $tile_choice = $result['tile_choice'];
        } elseif($type == 'hawk1') {
            $this->validateSelection('tile', $selected_type);
            $this->performPeek($selected_id, 'hawk1');
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'hawk2') {
            $this->validateSelection('tile', $selected_type);
            $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            if ($this->hawk2PeekAllowed($player_tile, $this->tiles->getCard($selected_id))) {
                $this->performPeek($selected_id, 'effect');
            }
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'juicer1') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            $flipped = $this->getFlippedTiles($this->tileFloor($tile));
            if (!isset($flipped[$tile['id']])) {
                throw new BgaUserException(clienttranslate('You must reveal the tile first before setting an alarm'));
            }
            $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            if (!$this->isTileAdjacent($tile, $player_tile, null, 'guard')) {
                throw new BgaUserException(clienttranslate('This tile is not adjacent'));
            }
            $special_choice = $this->triggerAlarm($tile);
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'peekhole') {
            $this->validateSelection('tile', $selected_type);
            $this->performPeek($selected_id, 'peekhole');
        } elseif($type == 'peterman2') {
            $this->validateSelection('button', $selected_type);
            $tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            $floor = $selected_id % 10;
            $other_tile = $this->findTileOnFloor($floor, $tile['location_arg']);
            $add_or_roll = floor($selected_id / 10);
            if ($add_or_roll == 0) {
                $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
                if ($actions_remaining < 2) {
                    throw new BgaUserException(clienttranslate("Adding a die requires 2 actions"));
                }
                $this->performAddSafeDie($other_tile);
                $this->incStateValue(GameStateValue::ActionsRemaining, -1);
            } else {
                $this->performSafeDiceRoll($other_tile, $other_tile['id']);
            }
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'raven1') {
            $this->validateSelection('tile', $selected_type);
            $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            $tile = $this->tiles->getCard($selected_id);
            if ($this->tileFloor($player_tile) != $this->tileFloor($tile)) {
                throw new BgaUserException(clienttranslate('Crow must be placed on your floor'));
            }
            $path = $this->findShortestPathClockwise($this->tileFloor($player_tile), $player_tile['location_arg'], $tile['location_arg']);
            if (count($path) <= 3) { // Includes starting tile
                $crow = array_values($this->tokensOfType(TokenType::Crow))[0];
                $this->moveToken($crow['id'], 'tile', $selected_id);
            } else {
                throw new BgaUserException(clienttranslate('Crow can be placed up to two tiles away'));
            }
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'spotter1') {
            $this->validateSelection('button', $selected_type);
            if ($selected_id == 2) {
                $player_tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
                $floor = $this->tileFloor($player_tile);
                $deck = DeckType::patrol($floor)->deckName();
                $top_patrol = $this->cards->getCardOnTop($deck);
                $this->cards->insertCardOnExtremePosition($top_patrol['id'], $deck, FALSE);
            }
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'spotter2') {
            $this->validateSelection('button', $selected_type);
            if ($selected_id == 2) {
                $top_event = $this->cards->getCardOnTop(DeckType::Events->deckName());
                $this->cards->insertCardOnExtremePosition($top_event['id'], DeckType::Events->deckName(), FALSE);
            }
            self::incStat(1, 'special_ability_use', self::getCurrentPlayerId());
        } elseif($type == 'thermal-bomb') {
            $this->validateSelection('button', $selected_type);
            // $tile = $this->getPlayerTile(self::getCurrentPlayerId());
            $tile = $this->getPlayerTile($this->getCurrentPlayerIdCustom());
            $this->pickTokensForTile(TokenType::Thermal, $tile['id']);
            // Can make thermal bomb go to roof
            if ($selected_id != $this->getFloorCount() + 1) {
                $other_tile = $this->findTileOnFloor($selected_id, $tile['location_arg']);
                $this->pickTokensForTile(TokenType::Thermal, $other_tile['id']);
            }
            if ($this->stateValue(GameStateValue::EmpPlayer) == 0) {
                $special_choice = $this->triggerAlarm($tile, FALSE, TRUE);
            }
        } elseif ($type == 'virus') {
            $this->validateSelection('tile', $selected_type);
            // $tile = $this->tiles->getCard($selected_id);
            $tile = $this->getTile($selected_id);
            if ($tile['flipped'] === '0') {
                throw new BgaUserException(clienttranslate("You must reveal the tile first"));
            }
            if (!TileType::of($tile)->isComputer()) {
                throw new BgaUserException(clienttranslate("Tile is not a computer"));
            }
            $existing = $this->tokensInTile(TokenType::Hack, $tile['id']);
            $nbr = $existing <= 3 ? 3 : 6 - $existing;
            $this->pickTokensForTile(TokenType::Hack, $tile['id'], $nbr);
        } elseif ($type == 'crystal-ball') {
            $card_names_displayed = [];
            foreach (array_reverse($selected_id) as $event_card_id) {
                $this->cards->insertCardOnExtremePosition($event_card_id, DeckType::Events->deckName(), true);
                $event_card = $this->cards->getCard($event_card_id);
                $card_names_displayed[] = $this->getDisplayedCardName($this->getCardType($event_card));
            }
            $this->bga->notify->all('message', clienttranslate('Crystal Ball: ${player_name} changed order of upcoming events to ${card_names_displayed}'), [
                'player_name' => self::getCurrentPlayerName(),
                'card_names_displayed' => implode(', ', array_reverse($card_names_displayed)),
            ]);
        } elseif ($type == 'stethoscope') {
            [$old_value, $new_value] = $selected_id;
            self::DbQuery("UPDATE token SET card_type_arg=$new_value WHERE card_type='die' AND card_type_arg=$old_value LIMIT 1");
            $this->bga->notify->all('message', clienttranslate('Stethoscope: ${player_name} changed one die from ${old_value} to ${new_value}'), [
                'player_name' => self::getCurrentPlayerName(),
                'old_value' => $old_value,
                'new_value' => $new_value
            ]);
            $this->applyDieRoll();
            $this->incStateValue(GameStateValue::ActionsRemaining, -1);
        } elseif ($type == 'throw-voice') {
            $this->validateSelection('tile', $selected_type);
            $tile = $this->tiles->getCard($selected_id);
            $player_token = $this->getPlayerToken($current_player_id);
            $player_tile = $this->getPlayerTile($current_player_id, $player_token);
            $floor = $this->tileFloor($player_tile);
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
            $patrol_tile = $this->tiles->getCard($patrol_token['location_arg']);
            // $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            // $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            if ($this->isTileAdjacent($tile, $patrol_tile, null, 'guard')) {
                $this->moveToken($patrol_token['id'], 'tile', $selected_id, TRUE);
                // $this->performGuardMovementEffects($guard_token, $selected_id, TRUE);
            } else {
                throw new BgaUserException(clienttranslate("This tile is not adjacent to the guard destination"));
            }
        }
        if ($card['type'] != CardType::Character->value) {
            if ($discard) {
                $this->cards->moveCard($card['id'], $card['type'] == CardType::Tool->value ? DeckType::Tools->discardName() : DeckType::Events->discardName());
            }
            if ($card['type'] == CardType::Tool->value) {
                // $this->notifyPlayerHand(self::getCurrentPlayerId(), array($card['id']));
                $this->notifyPlayerHand($this->getCurrentPlayerIdCustom(), array($card['id']));
            }
        }
        return array(
            'tile_choice' => $tile_choice,
            'special_choice' => $special_choice,
        );
    }

    function handleSelectTileChoice($selected) {
        // $player_id = self::getCurrentPlayerId();
        $player_id = $this->getCurrentPlayerIdCustom();
        $tile = $this->tiles->getCard($this->stateValue(GameStateValue::TileChoice));
        $type = TileType::of($tile);
        $special_choice = FALSE;
        if ($selected == 0) { // trigger
            $special_choice = $this->triggerAlarm($tile);
        } elseif($selected == 1) { // hack
            if (!$this->canHack($tile)) {
                throw new BgaUserException(clienttranslate('Cannot hack this tile'));
            }
            $tokens = $this->getPlacedTokens(array(TokenType::Hack));
            $computer_tile = array_values($this->tiles->getCardsOfType($type->computer()->value))[0];
            if (isset($tokens[$computer_tile['id']])) {
                // Use computer first
                $to_move = $tokens[$computer_tile['id']][0];
            } else {
                // Otherwise use hacker2's token
                $to_move = array_values($this->getPlacedTokens(array(TokenType::Hack), 'card'))[0][0];
            }
            $this->moveToken($to_move, 'deck');
            $this->bga->notify->all('message', clienttranslate('${player_name} used a hack token'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
        } elseif($selected == 2) { // Extra action
            if (!$this->canUseExtraAction($player_id, $tile)) {
                throw new BgaUserException(clienttranslate('Cannot use an extra action to enter this tile'));
            }
            $gemstone_penalty = $this->getGemstonePenalty($player_id, $tile, TRUE);
            // Take an extra 1 (or 2 for gemstone). Another 1 is always taken
            $this->incStateValue(GameStateValue::ActionsRemaining, -1 - $gemstone_penalty);
            $this->bga->notify->all('message', clienttranslate('${player_name} used an extra action'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
        }
        return array(
            'tile' => $tile,
            'special_choice' => $special_choice
        );
    }

    function performPeek($tile_id, $variant='peek') {
        $current_player_id = self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        $to_peek = $this->tiles->getCard($tile_id);
        $floor = $this->tileFloor($to_peek);
        $flipped = $this->getFlippedTiles($floor);
        $patrol_names = $this->patrolNames();
        if (isset($flipped[$to_peek['id']])) {
            if ($variant == 'effect') {
                // Tile is already flipped, do nothing
                return;
            } else {
                throw new BgaUserException(clienttranslate("Tile is already visible"));
            }
        }
        $walls = $this->getWalls();
        if ($variant != 'effect' && !$this->isTileAdjacent($to_peek, $player_tile, $walls, $variant)) {
            throw new BgaUserException(clienttranslate("Tile is not adjacent"));
        }

        $this->handleTilePeek($to_peek);
        $tile_name = $patrol_names[$to_peek['location_arg']]['name'];
        $tile_type = $this->getDisplayedCardName($to_peek['type']);
        // $players = self::loadPlayersBasicInfos();
        $players = $this->loadPlayersInfos();
        $message = $variant == 'effect' ? 
            clienttranslate('${player_name} reveal tile ${tile_name} (${tile_type}) on floor ${floor}') : 
            clienttranslate('${player_name} peeked tile ${tile_name} (${tile_type}) on floor ${floor}');
        $this->bga->notify->all('message', $message, [
            'i18n' => ['tile_type'],
            'player_name' => $players[$current_player_id]['player_name'],
            'tile_name' => $tile_name,
            'tile_type' => $tile_type,
            'floor' => $floor,
        ]);
        $this->flipTile( $floor, $to_peek['location_arg'] );
    }

    function setupGuardToken($guard_token, $floor) {
        if ($guard_token['location'] == 'deck') {
            $this->setupPatrol($guard_token, $floor);
            $this->nextPatrol($floor, TRUE);
        }
    }

    function performMove($tile_id, $context='action', $player_id = null) {
        $current_player_id = $player_id != null ? $player_id : self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        $to_move = $this->tiles->getCard($tile_id);
        $floor = $this->tileFloor($to_move);
        $flipped = $this->getFlippedTiles($floor);
        $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
        $acrobat_entered = 0;

        if (!$this->isTileAdjacent($to_move, $player_tile, null, $context)) {
            throw new BgaUserException(clienttranslate("Tile is not adjacent"));
        }
        if ($context == 'acrobat1') {
            if ($guard_token['location'] != 'tile' || $guard_token['location_arg'] != $tile_id) {
                throw new BgaUserException(clienttranslate("Tile does not contain a guard"));
            }
            $acrobat_entered = 1;
        }

        $flipped_this_turn = !isset($flipped[$to_move['id']]);
        if ($flipped_this_turn) {
            $this->handleTilePeek($to_move);
        }
        $move_result = $this->handleTileMovement($to_move, $player_tile, $player_token, $guard_token, $flipped_this_turn, $context);
        $this->flipTile( $floor, $to_move['location_arg'] );
        $invisible_suit = $this->stateValue(GameStateValue::InvisibleSuitActive) == 1;
        if ($move_result['perform_move']) {
            $this->setStateValue(GameStateValue::AcrobatEnteredGuardTile, $acrobat_entered);
            if (!$invisible_suit && !$acrobat_entered) {
                $this->handlePlayerEnteredGuardSight($to_move, $current_player_id);
            }
        }
        if (!$invisible_suit) {
            $this->checkCameras(array('player_id'=>$player_token['id']));
        }
        return $move_result;
    }

    function allPlayersEscaped() {
        $players_count = $this->getSoloMultiCharacters() > 1 ? $this->getSoloMultiCharacters() : self::getPlayersNumber();
        return count($this->tokensOfTypeInLocation(TokenType::Player, null, 'roof')) == $players_count;
    }

    function isKittyEscaped() {
        return count($this->tokensOfTypeInLocation(TokenType::Cat, null, 'tile')) > 0;
    }

    function checkWin() {
        $safes_needed = $this->scenario() === Scenario::OfficeJob ? 2 : 3;
        $all_safes_opened = $this->openSafes() == $safes_needed;
        $all_loot_escaped = count($this->cards->getCardsOfTypeInLocation(CardType::Loot->value,null, 'tile')) == 0 &&
            !$this->isKittyEscaped();
        return $all_safes_opened && $all_loot_escaped;
    }

    function performAddSafeDie($tile) {
        if (TileType::of($tile) !== TileType::Safe) {
            throw new BgaUserException(clienttranslate("Tile is not a safe"));
        }
        if ($this->tokensInTile(TokenType::Open, $tile['id'])) {
            throw new BgaUserException(clienttranslate("Safe is already open"));
        }
        $floor = $this->tileFloor($tile);
        $die_num = $this->getSafeDie($tile['id']);
        if ($die_num == 6) {
            throw new BgaUserException(clienttranslate("You cannot add more than 6 die on a safe"));   
        }
        $safe_token = array_values($this->tokensOfTypeInLocation(TokenType::Crack, null, 'tile', $tile['id']));
        if (count($safe_token) > 0) {
            $safe_token = $safe_token[0];
        } else {
            $safe_token = array_values($this->tokensOfTypeInLocation(TokenType::Crack, $floor, 'deck'))[0];
            $this->moveToken($safe_token['id'], 'tile', $tile['id']);
        }
        $this->setSafeDie(++$die_num, $tile['id']);
        $this->bga->notify->all('safeDieIncreased', '', array(
            'die_num' => $die_num,
            'token' => $safe_token,
            'floor' => $floor
        ));
        $this->bga->notify->all('message', clienttranslate('${player_name} added a die to the safe on floor ${floor}'), [
            'player_name' => self::getCurrentPlayerName(),
            'floor' => $floor
        ]);
    }

    function createTrade($player1, $player2) {
        self::DbQuery("INSERT INTO trade(current_player, other_player, deleted) VALUES ($player1, $player2, 0)");
        return self::DbGetLastId();
    }

    function createTradeCards($trade_id, $player_id, $card_ids) {
        if (count($card_ids) > 0) {
            $sql = 'INSERT INTO trade_cards (trade_id, player_id, card_id) VALUES ';
            $values = array();
            foreach ($card_ids as $card_id) {
                $values [] = "($trade_id,$player_id,$card_id)";
            }
            $sql .= implode(',', $values);
            self::DbQuery($sql);
        }
    }

    function getTrade() {
        return self::getObjectFromDB("SELECT * FROM trade WHERE deleted = 0");
    }

    function getTradeCards($trade_id, $player_id) {
        $sql = <<<SQL
            SELECT card.card_id id, card.card_type type, card.card_type_arg type_arg, card.card_location location, card.card_location_arg location_arg
            FROM trade_cards
            INNER JOIN card ON card.card_id = trade_cards.card_id
            WHERE trade_cards.trade_id = $trade_id AND trade_cards.player_id = $player_id
SQL;
        return self::getCollectionFromDB($sql);
    }

    function deleteTrade() {
        self::DbQuery("UPDATE trade SET deleted = 1 WHERE deleted = 0");
    }

    function parseIdList($id_arg) {
        // Removing last ';' if exists
        if( substr( $id_arg, -1 ) == ';' )
            $id_arg = substr( $id_arg, 0, -1 );
        if( $id_arg == '' )
            $ids = array();
        else
            $ids = explode( ';', $id_arg );
        return $ids;
    }

    function handleSelectPlayerChoice($current_player_id, $type, $selected) {
        if ($type == 'trade') {
            $current_player_token = $this->getPlayerToken($current_player_id);
            $player_tokens = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $current_player_token['location_arg']);
            if (!isset($player_tokens[$selected])) {
                throw new BgaUserException(clienttranslate('Selected player is not in your tile'));
            }
            if ($player_tokens[$selected]['type_arg'] == $current_player_id) {
                throw new BgaUserException(clienttranslate('You cannot trade with yourself'));
            }
            $this->createTrade($current_player_id, $player_tokens[$selected]['type_arg']);
            $this->gamestate->nextState('proposeTrade');
        } else if ($type == 'rook1') {
            $meeple = $this->tokens->getCard($selected);
            if (TokenType::of($meeple) !== TokenType::Player) {
                throw new BgaUserException(clienttranslate('Must choose a player token'));
            }
            if ($meeple['type_arg'] == $current_player_id) {
                throw new BgaUserException(clienttranslate('You cannot choose yourself'));
            }
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::Rook1);
            $this->setStateValue(GameStateValue::SpecialChoiceArg, $meeple['type_arg']); // Rook 1
            $this->gamestate->nextState('specialChoice');
        } else if ($type == 'rook2') {
            $meeple = $this->tokens->getCard($selected);
            if (TokenType::of($meeple) !== TokenType::Player) {
                throw new BgaUserException(clienttranslate('Must choose a player token'));
            }
            if ($meeple['type_arg'] == $current_player_id) {
                throw new BgaUserException(clienttranslate('You cannot choose yourself'));
            }
            if ($meeple['location'] == 'roof') {
                throw new BgaUserException(clienttranslate('You cannot trade places with an escaped player'));
            }
            if ($meeple['location'] == 'hand') {
                throw new BgaUserException(clienttranslate('You cannot trade places when the other player has not entered yet the building'));
            }
            $player_token = $this->getPlayerToken($current_player_id);
            $tmp_location = $meeple['location_arg'];
            $this->moveToken($meeple['id'], 'tile', $player_token['location_arg']);
            $this->moveToken($player_token['id'], 'tile', $tmp_location);
            $rook_tile = $this->getTile($player_token['location_arg']);
            $other_tile = $this->getTile($tmp_location);
            $players = $this->loadPlayersInfos();
            $patrol_names = $this->patrolNames();
            $this->bga->notify->all('message', clienttranslate('The Rook Advanced: ${player_name} (${rook_tile_name} on floor ${rook_tile_floor}) trades places with ${other_player_name} (${other_tile_name} on floor ${other_tile_floor})'), [
                'player_name' => self::getActivePlayerName(),
                'rook_tile_name' => $patrol_names[$rook_tile['location_arg']]['name'],
                'rook_tile_floor' => $this->tileFloor($rook_tile),
                'other_player_name' => $players[$meeple['type_arg']]['player_name'],
                'other_tile_name' => $patrol_names[$other_tile['location_arg']]['name'],
                'other_tile_floor' => $this->tileFloor($other_tile),
            ]);
            $this->endAction();
        } else if ($type == 'squeak') {
            $meeple = $this->tokens->getCard($selected);
            if (TokenType::of($meeple) !== TokenType::Player) {
                throw new BgaUserException(clienttranslate('Must choose a player token'));
            }
            $tile = $this->getPlayerTile($current_player_id);
            $floor = $this->tileFloor($tile);
            $selected_player_id = $meeple['type_arg'];
            $selected_player_tile = $this->getPlayerTile($selected_player_id);
            if (count($this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $selected_player_tile['id'])) == 0)
                throw new BgaUserException(clienttranslate("You must choose a tile with a player"));
            if ($selected_player_tile['location'] != "floor$floor")
                throw new BgaUserException(clienttranslate("You must choose a player tile on the same floor"));
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            $path = $this->findShortestPathClockwise($floor, $guard_tile['location_arg'], $selected_player_tile['location_arg']);
            if (count($path) > $this->stateValue(GameStateValue::PlayerChoiceArg))
                throw new BgaUserException(clienttranslate("You must choose one of the closest players"));
            // If guard and players are on the same tile, do not move players
            if (count($path) > 1) {
                $this->performGuardMovementEffects($guard_token, $path[1]);
                $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $floor))[0];
                // If squeak move led the guard to his final destination, draw a new patrol card
                if ($path[1] == $patrol_token['location_arg']) {
                    $this->nextPatrol($floor, TRUE);                
                }
            }
            $this->endAction();
        }
    }

    function handleSelectSpecialChoice($type, $choice_arg, $selected) {
        if ($type == 'rook1') {
            $multi_characters = $this->getSoloMultiCharacters();
            $players = $this->loadPlayersInfos();
            $active_player_id = $this->getCurrentPlayerIdCustom();
            // Must store tile_choice destination
            $this->setStateValue(GameStateValue::RookDestinationTile, $selected);
            if ($multi_characters > 1) {
                $player_name = $players[$active_player_id]['player_name'];
            } else {
                // Must switch to another state to ask chosen player confirmation
                $this->setStateValue(GameStateValue::CurrentPlayer, $active_player_id);
                $player_name = self::getActivePlayerName();
            }
            $this->bga->notify->all('message', clienttranslate('The Rook: ${player_name} wants to move ${other_name}'), [
                'player_name' => $player_name,
                'other_name' => $players[$choice_arg]['player_name'],
            ]);      
            $this->gamestate->nextState('switchRookMove');
        } elseif ($type == 'closest_alarm') {
            // Check each floor because shoplifting or cameras can trigger several alarms on multiple floors at a time
            $selected_card = $this->tiles->getCard($selected);
            $selected_floor = $this->tileFloor($selected_card);
            // $active_player_id = $this->getCurrentPlayerIdCustom();
            // $player_tile = $this->getPlayerTile($active_player_id);
            // $floor = $this->tileFloor($player_tile);
            $alarm_tiles = $this->getFloorClosestAlarmTiles($selected_floor);
            if (count($alarm_tiles) <= 1)
                throw new BgaUserException(clienttranslate("You must choose an alarm on the right floor"));
            if (!in_array($selected, $alarm_tiles))
                throw new BgaUserException(clienttranslate("You must choose one of the closest alarms"));
            $patrol_token = array_values($this->tokensOfType(TokenType::Patrol, $selected_floor))[0];
            $this->moveToken($patrol_token['id'], 'tile', $selected, TRUE);
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::None);    
            // Resume to the expected state
            $expected_state = $this->state_after_alarms[$this->stateValue(GameStateValue::StateAfterAlarm)];
            $this->setStateValue(GameStateValue::StateAfterAlarm, 0);
            $this->gamestate->nextState( $expected_state );
        } elseif ($type == 'rigger') {
            $tool = $this->cards->getCard($choice_arg);
        }
    }

    function moveCatToken($player_id) {
        $player_tile = $this->getPlayerTile($player_id);
        $floor = $this->tileFloor($player_tile);
        $tiles = $this->getTiles($floor);
        $shortest_path = null;
        foreach ($tiles as $tile_id => $tile) {
            if (in_array(TileType::of($tile), array(TileType::Camera, TileType::Detector, TileType::Fingerprint, TileType::Laser, TileType::Motion, TileType::Thermo), true)) {
                $path = $this->findShortestPathClockwise($floor, $player_tile['location_arg'], $tile['location_arg']);
                if (count($path) > 1 && ($shortest_path == null || count($shortest_path) > count($path))) {
                    $shortest_path = $path;
                }
            }
        }
        if ($shortest_path != null) {
            $dest_id = array_values($shortest_path)[1];
            $this->pickTokensForTile(TokenType::Cat, $dest_id);
        }
    }

    function showRiggerToolSelection() {
        return $this->getPlayerCharacter(null, 'rigger1') || $this->getPlayerCharacter(null, 'rigger2');
    }

    function skipEscapedPlayers($player_id) {
        $player_token = $this->getPlayerToken($player_id);
        while ($player_token['location'] == 'roof') {
            // $player_id = self::activeNextPlayer();
            $player_id = $this->activeNextPlayerCustom();
            $player_token = $this->getPlayerToken($player_id);
        }
        return $player_id;
    }

    function reshuffleDeckIfEmpty(DeckType $deck) {
        $count = $this->cards->countCardInLocation($deck->deckName());
        if ($count == 0) {
            $this->cards->moveAllCardsInLocation($deck->discardName(), $deck->deckName());
            $this->cards->shuffle($deck->deckName());
        }
    }

    function characterActionEnabled($current_player_id, $character) {
        $type = $character['name'];
        $used = $this->stateValue(GameStateValue::CharacterAbilityUsed);
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1 && in_array($type, array('hacker2', 'peterman1', 'peterman2', 'rook1', 'rook2', 'spotter1', 'spotter2'))) {
            return FALSE;
        } else if ($used && in_array($type, array('hawk1', 'hawk2', 'juicer2', 'rook1', 'spotter1', 'spotter2'))) {
            return FALSE;
        } else if($type == 'acrobat1') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $floor = $this->tileFloor($player_tile);
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            // Check guard on the same floor
            $guard_adjacent = $this->isTileAdjacent($guard_tile, $player_tile, null, 'acrobat1_enabled');
            // Check guard on upper or lower floor through stairs
            $max_floor = $this->getFloorCount();
            if ($floor > 1) { // check lower floor
                $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor - 1))[0];
                $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
                $guard_adjacent = $guard_adjacent ||
                    $this->stairsAreAdjacent($player_tile, $guard_tile) || 
                    $this->stairsAreAdjacent($guard_tile, $player_tile) ||
                    $this->thermalBombStairsAreAdjacent($player_tile, $guard_tile) ||
                    $this->walkwayIsAdjacent($player_tile, $guard_tile);
            }
            if ($floor < $max_floor)  { // check upper floor
                $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor + 1))[0];
                $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
                $guard_adjacent = $guard_adjacent ||
                    $this->stairsAreAdjacent($player_tile, $guard_tile) || 
                    $this->stairsAreAdjacent($guard_tile, $player_tile) ||
                    $this->thermalBombStairsAreAdjacent($player_tile, $guard_tile) ||
                    $this->walkwayIsAdjacent($player_tile, $guard_tile);
            }
            // Check if a guard is on other side of the Service Duct
            if (TileType::of($player_tile) === TileType::ServiceDuct) {
                $service_ducts = self::getCollectionFromDB("SELECT card_id id, card_type type, card_location location, card_location_arg location_arg FROM tile WHERE flipped=1 AND card_type='service-duct'");
                foreach ($service_ducts as $id => $service_duct) {
                    if ($id == $player_tile['id'])
                        continue;
                    $guard_token = array_values($this->tokensOfType(TokenType::Guard, $this->tileFloor($service_duct)))[0];;
                    $guard_adjacent = $guard_adjacent || ($guard_token['location_arg'] == $id);
                }
            }
            if ( !$guard_adjacent ) {
                return FALSE;
            }
        } else if($type == 'acrobat2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $plan = new BurgleBrosFloorPlan($this->getSquareSize(), $this->getWalls());
            if ($plan->isInteriorCell($player_tile['location_arg'])) {
                return FALSE;
            }
            if($this->stateValue(GameStateValue::ActionsRemaining) < 3) {
                return FALSE;
            }
        } else if($type == 'hacker2') {
            if (count($this->tokensOfTypeInLocation(TokenType::Hack, null, 'card', $character['id'])) > 0) {
                return FALSE;
            }
        } else if($type == 'hawk1') {
            $player_tile = $this->getPlayerTile($current_player_id);
            if (count($this->getPeekableTiles($player_tile, 'hawk1')) == 0) {
                return FALSE;
            }
        } else if($type == 'juicer2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $tile_alarms = $this->tokensOfTypeInLocation(TokenType::Alarm, null, 'tile', $player_tile['id']);
            $character_alarms = $this->tokensOfTypeInLocation(TokenType::Alarm, null, 'card', $character['id']);
            
            if (count($character_alarms) > 0) {
                if (count($tile_alarms) > 0) {
                    return FALSE;
                }
            } else if (count($tile_alarms) == 0) {
                return FALSE;
            }
        } else if($type == 'peterman2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $found = FALSE;
            $max_floor = $this->getFloorCount();
            for ($floor=1; $floor <= $max_floor; $floor++) {
                if (abs($floor - $this->tileFloor($player_tile)) == 1) {   
                    $tiles = $this->getTiles($floor);
                    foreach ($tiles as $tile) {
                        if (TileType::of($tile) === TileType::Safe && $tile['location_arg'] == $player_tile['location_arg'] && !$this->tokensInTile(TokenType::Open, $tile['id'])) {
                            $found = TRUE;
                            break;
                        }
                    }
                }
                if ($found) {
                    break;
                }
            }
            return $found;
        } else if($type == 'raven2') {
            $crow = array_values($this->tokensOfType(TokenType::Crow))[0];
            $player_tile = $this->getPlayerTile($current_player_id);
            if ($crow['location'] == 'tile' && $crow['location_arg'] == $player_tile['id']) {
                return FALSE;
            }
        } else if($type == 'rigger2') {
            $stealth = $this->getPlayerStealth($current_player_id);
            if ($stealth <= 0) {
                return FALSE;
            }
        } else if($type == 'rook2') {
            if ($this->stateValue(GameStateValue::FirstAction) != 1) {
                return FALSE;
            }
        }
        // I purposely am not checking spotter1/spotter2. I want those to show the error message.

        return TRUE;
    }

    function getCardTokens($tile_id=null) {
        $cards = $this->cards->getCardsInLocation('tile', $tile_id, 'card_location_arg');
        $tokens = [];
        foreach ($cards as $card_id => $card) {
            if (!isset($tokens[$card['location_arg']])) {
                $tokens[$card['location_arg']] = ['type'=>$card['type'],'count'=>0];
            }
            $token = &$tokens[$card['location_arg']];
            if ($token['type'] == CardType::Tool->value) {
                // Overwrite if previous was a tool
                $token['type'] = $card['type'];
            }
            $token['count']++;
        }
        // Add token on unused but flipped Laboratories
        $laboratories = self::getCollectionFromDB("SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg, safe_die, flipped FROM tile WHERE card_type='laboratory'");
        $tile_entered = $this->stateValue(GameStateValue::LaboratoryTileEntered);
        foreach ($laboratories as $lab_tile_id => $tile) {
            $tile_bit = 1 << $tile['safe_die'];
            if (($tile_entered & $tile_bit) == 0x0 && ($tile['flipped'] == 1 || $lab_tile_id == $tile_id)) {
                $tokens[$lab_tile_id] = ['type'=>CardType::Loot->value,'count'=>0];  // Mark the lab's unclaimed tool (client renders this type with the loot sprite)
            }
        }
        return $tokens;
    }

    function notifyGuardMovement($floor, $movement, $alarms, $has_event=FALSE) {
        $alarm_tiles = $this->getFloorAlarmTiles($floor);
        $has_alarms = count($alarm_tiles) > 0;
        if ($has_alarms || $has_event) {
            if ($has_alarms && $has_event) {
                $msg = clienttranslate('Guard on floor ${floor} is moving ${movement} spaces including ${alarms} alarms and an event card');
            } else if ($has_alarms) {
                $msg = clienttranslate('Guard on floor ${floor} is moving ${movement} spaces including ${alarms} alarms');
            } else if ($has_event) {
                $msg = clienttranslate('Guard on floor ${floor} is moving ${movement} spaces including an event card');
            }
        } else {
            $msg = clienttranslate('Guard on floor ${floor} is moving ${movement} spaces');
        }
        $this->bga->notify->all('message', $msg, [
            'floor' => $floor,
            'movement' => $movement,
            'alarms' => $alarms,
        ]);
    }

    /* DEBUG */
    function flipTilesDebug($floor = null, $location_arg = null) {
        // Can flip tiles 1 at a time or (withou arguments) all the tiles of the game
        if ($location_arg) {
            $this->flipTile($floor, $location_arg);
        } else if ($floor) {
            $size = $this->getSquareSize();
            $tile_count = $size * $size - 1;
            for ($i=0; $i <= $tile_count ; $i++) { 
                $this->flipTile($floor, $i);
            }
        } else {
            $tiles = self::getCollectionFromDB("SELECT card_id id, card_type type, card_location location, card_location_arg location_arg FROM tile WHERE flipped=0 AND NOT card_location='deck'");
            foreach ($tiles as $id => $tile) {
                $this->flipTile($this->tileFloor($tile), $tile['location_arg']);
            }
        }
    }

    function switchTilesDebug($floor_1, $location_arg_1, $floor_2, $location_arg_2) {
        // Can switch two tiles of the game switchTilesDebug(1,0,1,1)
        $tile_id_1 = $this->findTileOnFloor($floor_1, $location_arg_1)['id'];
        $tile_id_2 = $this->findTileOnFloor($floor_2, $location_arg_2)['id'];
        self::DbQuery("UPDATE tile SET card_location='floor$floor_2', card_location_arg=$location_arg_2 WHERE card_id=$tile_id_1" );
        self::DbQuery("UPDATE tile SET card_location='floor$floor_1', card_location_arg=$location_arg_1 WHERE card_id=$tile_id_2" );

        $this->bga->notify->all('tileFlipped', '', array(
            'tile' => $this->findTileOnFloor($floor_1, $location_arg_1),
            'floor' => $floor_1,
            'undo_allowed' => $this->stateValue(GameStateValue::UndoAllowed),
        ));
        $this->bga->notify->all('tileFlipped', '', array(
            'tile' => $this->findTileOnFloor($floor_2, $location_arg_2),
            'floor' => $floor_2,
            'undo_allowed' => $this->stateValue(GameStateValue::UndoAllowed),
        ));
    }

    function getStatDebug($stat_name, $player_id = null) {
         var_dump($this->getStat($stat_name, $player_id));
    }

    public function activatePlayer() {
        // Set current player for multiplayer game
        $this->setStateValue(GameStateValue::CurrentPlayer, 2318200);
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in burglebros.action.php)
    */
    function randomizeWalls(string $floor) {
        if ($floor === 'start') {
            $this->gamestate->nextState('startGame');
        } else {
            if ($floor === 'all') {
                $this->board->randomizeAllWalls();
            } else {
                $this->board->randomizeWalls((int) $floor);
            }
            if ($floor === 'all') {
                $msg = clienttranslate("New random walls are generated on every floor");
            } else {
                $msg = clienttranslate('New random walls are generated on floor ${floor}');
            }
            // Update tiles to update shaft
            $tiles = [];
            $max_floor = $this->getFloorCount();
            for ($i=1; $i <= $max_floor; $i++) { 
                $tiles["floor$i"] = $this->getTiles($i);
            }
            $this->bga->notify->all('updateWalls', $msg, [
                'floor' => $floor,
                'walls' => $this->getWalls(),
                'tiles' => $tiles,
            ]);
        }
    }

    function peek( $tile_id ) {
        self::checkAction('peek');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        }
        $this->performPeek($tile_id);
        $this->endAction();
    }

    function move( $tile_id, $context='action' ) {
        self::checkAction('move');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        $tile_choice = FALSE;
        if ($context == 'acrobat1') {
            $current_player_id = self::getCurrentPlayerIdCustom();
            $character = $this->getPlayerCharacter($current_player_id);
            if ($this->getCardType($character) != 'acrobat1')
                throw new BgaUserException(clienttranslate("You are not the Acrobat"));
            $this->setStateValue(GameStateValue::CardChoice, $character['id']);
            $this->selectCardChoice('tile', [$tile_id], FALSE);
            $this->endAction(0);
        } elseif ($actions_remaining < 1) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        } else {
            $result = $this->performMove($tile_id, $context);
            $tile_choice = $result['tile_choice'];
            $special_choice = $result['special_choice'];
            if ($this->stateValue(GameStateValue::StealthDepleted)) {
                $this->gamestate->nextState('gameOver');
            } elseif ($tile_choice) {
                $this->setStateValue(GameStateValue::MoveDecreaseAfterAlarm, 1);
                $this->setStateValue(GameStateValue::TileChoice, $tile_choice);
                $this->gamestate->nextState('tileChoice');
            } elseif ($special_choice) {
                $this->incStateValue(GameStateValue::ActionsRemaining, -1);
                $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
                $this->setStateValue(GameStateValue::StateAfterAlarm, State::EndAction);
                $this->gamestate->nextState('chooseAlarm');
            } else {
                $this->endAction();
            }
        }
    }

    function addSafeDie() {
        self::checkAction('addSafeDie');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 2) {
            throw new BgaUserException(clienttranslate("Adding a die requires 2 actions"));
        }
        $current_player_id = self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        
        $this->performAddSafeDie($player_tile);
        $this->endAction(2);
    }

    function rollSafeDice() {
        self::checkAction('rollSafeDice');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        }
        $current_player_id = self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);

        if (!$this->performSafeDiceRoll($player_tile))
            $this->endAction();
    }

    function hack() {
        self::checkAction('hack');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        }
        $current_player_id = self::getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        if (!TileType::of($player_tile)->isComputer()) {
            throw new BgaUserException(clienttranslate("Tile is not a computer"));
        }
        $existing = $this->tokensOfTypeInLocation(TokenType::Hack, null, 'tile', $player_token['location_arg']);
        if (count($existing) >= 6) {
            throw new BgaUserException(clienttranslate("Only 6 hack tokens can be added to this tile"));
        }
        $this->pickTokensForTile(TokenType::Hack, $player_token['location_arg']);
        $this->bga->notify->all('message', clienttranslate('${player_name} added a hack token'), [
            'player_name' => self::getCurrentPlayerName()
        ]);
        $this->endAction();
    }

    function playCard($card_id) {
        self::checkAction('playCard');

        // $current_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $card = $this->cards->getCard($card_id);
        if ($this->gamestate->getCurrentMainState()->name == 'chooseCharacter') {
            if ($this->bga->tableOptions->get(GameOption::CharacterAssignment->value) !== CharacterAssignment::RandomAdvanced->value &&
                ($card['location'] == 'hand' || $card['location'] == 'characters_oop') )
                throw new BgaUserException(clienttranslate("This character is already taken by another player"));
        } elseif ($card['location'] != 'hand' || $card['location_arg'] != $current_player_id) {
            throw new BgaUserException(clienttranslate("Card is not in your hand"));
        }

        if ($card['type'] == CardType::Character->value) {
            if ($this->gamestate->getCurrentMainState()->name != 'chooseCharacter')
                throw new BgaUserException(clienttranslate("You cannot change your character once the game has started"));
            // Character choice, player chose a character
            $character = $this->cards->getCard($card_id);
            $type_arg = $character['type_arg'] % 2 == 0 ? $character['type_arg'] - 1: $character['type_arg'] + 1;
            $other_side_id = key($this->cards->getCardsOfType(CardType::Character->value,$type_arg));
            $this->cards->moveCard($card_id, 'hand', $current_player_id);
            $this->cards->moveCard($other_side_id, 'characters_oop');
            if ($this->getCardType($character) == 'rigger1') {
                $type_arg = $this->getCardTypeForName(CardType::Tool,'dynamite');
                $dynamite = array_values($this->cards->getCardsOfType(CardType::Tool->value,$type_arg))[0];
                $this->cards->moveCard($dynamite['id'], 'hand', $current_player_id);
            }
            // Update player hand and discard the other character card
            $discard_ids = array_keys($this->cards->getCardsInLocation(DeckType::Characters->deckName()));
            $this->notifyPlayerHand($current_player_id, array_merge($discard_ids, array($other_side_id)));
            $this->bga->notify->all('characterChosen', clienttranslate('${player_name} chooses to play ${character_name}'), [
                'i18n' => ['character_name'],
                'player_name' => self::getCurrentPlayerName(),
                'player_id' => $current_player_id,
                'character' => $character,
                'character_name' => $this->getDisplayedCardName($this->getCardType($card)),
            ]);
            // Remove chosen character from the other player hands
            $players = $this->loadPlayersInfos();
            foreach ($players as $player_id => $player) {
                if ($player_id != $current_player_id)
                    $this->notifyPlayerHand($player_id, array($card_id, $other_side_id));
            }
            // Check if playing solo with multiple characters
            $multi_characters = $this->getSoloMultiCharacters();
            if ($multi_characters > 1) {
                if (++$current_player_id >= self::getCurrentPlayerId() + $multi_characters) {
                    // Activate next player and enter state again
                    $human_player_id = self::getCurrentPlayerId();
                    $this->setStateValue(GameStateValue::CurrentPlayer, $human_player_id);
                    $this->bga->notify->all('activatePlayer', '', [
                        'player_id' => $human_player_id,
                    ]);
                    $this->gamestate->nextState('chooseCharacter');
                } else {
                    // Activate next player and enter state again
                    $this->setStateValue(GameStateValue::CurrentPlayer, $current_player_id);
                    $this->bga->notify->all('activatePlayer', '', [
                        'player_id' => $current_player_id,
                    ]);
                    $this->gamestate->nextState('nextPlayer');
                }
            } else {
                $this->gamestate->setPlayerNonMultiactive($current_player_id, 'chooseCharacter');
            }
        } else {
            // Player wants to use a tool
            if ($card['type'] != CardType::Tool->value) {
                throw new BgaUserException(clienttranslate("Card is not a tool"));
            }

            $bust = $this->getPlayerLoot('bust', $current_player_id);
            if ($bust) {
                throw new BgaUserException(clienttranslate("You may not use tools while holding the Bust"));
            }
            $human_player_id = self::getCurrentPlayerId();
            self::incStat(1, 'tools_used', $human_player_id);

            $choice = $this->handleToolEffect($current_player_id, $card);
            if ($choice) {
                $this->setStateValue(GameStateValue::CardChoice, $card['id']);
                $this->gamestate->nextState('cardChoice');
            } else {
                $this->cards->moveCard($card['id'], DeckType::Tools->discardName());
                $this->notifyPlayerHand($current_player_id, array($card['id']));
                $type = $this->getCardType($card);
                $this->bga->notify->all('message', clienttranslate('${player_name} played the ${title} card (${tooltip})'), [
                    'i18n' => ['title', 'tooltip'],
                    'card_id' => $card['id'],
                    'player_name' => self::getCurrentPlayerName(),
                    'title' => $this->getDisplayedCardName($type),
                    'tooltip' => $this->getCardTooltip($card)
                ]);
                
                $this->gamestate->nextState('endAction');
            }
        }
    }

    function selectCardChoice($type, $id, $check_action = TRUE) {
        if ($check_action)
            self::checkAction('selectCardChoice');
        $card_choice = $this->stateValue(GameStateValue::CardChoice);
        $card = $card_choice > 0 ? $this->cards->getCard($card_choice) : null;
        $result = $this->handleSelectCardChoice($card, $type, $id);
        $tile_choice = $result['tile_choice'];
        $special_choice = $result['special_choice'];
        if ($this->stateValue(GameStateValue::StealthDepleted)) {
            $this->gamestate->nextState('gameOver');
        } elseif ($tile_choice) {
            $this->setStateValue(GameStateValue::TileChoice, $tile_choice);
            $this->gamestate->nextState('tileChoice');
        } elseif ($special_choice) {
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
            $this->setStateValue(GameStateValue::StateAfterAlarm, State::PlayerTurn);
            $this->gamestate->nextState('chooseAlarm');
        } else {
            if ($card['type'] == CardType::Event->value) {
                $this->gamestate->nextState('endTurn');    
            } elseif ($card['type'] == CardType::Character->value) {
                $type = $this->getCardType($card);
                $this->bga->notify->all('message', clienttranslate('${player_name} used their character action'), [
                    'player_name' => self::getCurrentPlayerName()
                ]);
                $this->setStateValue(GameStateValue::CharacterAbilityUsed, 1);
                $human_player_id = self::getCurrentPlayerId();
                self::incStat(1, 'special_ability_use', $human_player_id);
                if (in_array($type, array('hacker2', 'spotter1', 'spotter2'))) {
                    $this->endAction(); // Spent action
                } else if ($type == 'peterman2') {
                    $this->endAction($id < 10 ? 2 : 1); // Spent 1 or 2 actions
                } else if ($type == 'acrobat2') {
                    $this->setStateValue(GameStateValue::ActionsRemaining, 0);
                    $this->endAction(0);
                } else {
                    $this->endAction(0); // Free action
                }
            } else {
                $card_type = $this->getCardType($card);
                // Move card is in handleSelectCardChoice
                // $this->cards->moveCard($card['id'], DeckType::Tools->discardName());
                $current_player_id = $this->getCurrentPlayerIdCustom();
                $this->notifyPlayerHand($current_player_id, array($card['id']));
                $this->bga->notify->all('message', clienttranslate('${player_name} played the ${title} card (${tooltip}'), [
                    'i18n' => ['title', 'tooltip'],
                    'card_id' => $card['id'],
                    'player_name' => self::getCurrentPlayerName(),
                    'title' => $this->getDisplayedCardName($card_type),
                    'tooltip' => $this->getCardTooltip($card),
                ]);
                $this->endAction(0);
            }
        }
    }

    function cancelCardChoice() {
        self::checkAction('cancelCardChoice');
        $card = $this->cards->getCard($this->stateValue(GameStateValue::CardChoice));
        if ($card['type'] == CardType::Loot->value) {
            throw new BgaUserException(clienttranslate('You may not cancel event effects'));
        } elseif ($this->getCardType($card) == 'stethoscope') {
            $this->applyDieRoll();
            $this->endAction();
        } else {
            // Don't run normal action decrease logic
            $this->gamestate->nextState('nextAction');
        }
    }

    function selectTileChoice($selected) {
        self::checkAction('selectTileChoice');
        $result = $this->handleSelectTileChoice($selected);
        $tile = $result['tile'];
        // Check if this is a Rook move
        $rook_destination_tile = $this->stateValue(GameStateValue::RookDestinationTile);
        if ($rook_destination_tile > 0) {
            $this->setStateValue(GameStateValue::RookDestinationTile, 0);
            $this->gamestate->nextState('switchRookMove');
        } else {
            $motion_exit = $this->stateValue(GameStateValue::MotionTileExitChoice);
            if (TileType::of($tile) === TileType::Motion && $motion_exit > 0) {
                $this->setStateValue(GameStateValue::TileChoice, $motion_exit);
                $this->gamestate->nextState('tileChoice');
            } elseif ($result['special_choice']) {
                $move_decrease = $this->stateValue(GameStateValue::MoveDecreaseAfterAlarm);
                if ($move_decrease > 0) {
                    $this->incStateValue(GameStateValue::ActionsRemaining, -$move_decrease);
                    $this->setStateValue(GameStateValue::MoveDecreaseAfterAlarm, 0);
                }
                $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
                $this->setStateValue(GameStateValue::StateAfterAlarm, State::EndAction);
                $this->gamestate->nextState('chooseAlarm');
            } else {
                $this->setStateValue(GameStateValue::TileChoice, 0);
                $this->endAction();
            }
            $this->setStateValue(GameStateValue::MotionTileExitChoice, 0);
        }
    }

    function characterAction() {
        self::checkAction('characterAction');
        $human_player_id = self::getCurrentPlayerId();
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $character = $this->getPlayerCharacter($current_player_id);
        $type = $this->getCardType($character);
        $used = $this->stateValue(GameStateValue::CharacterAbilityUsed);

        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1 && in_array($type, array('hacker2', 'rook1', 'spotter1', 'spotter2'))) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        } else if ($used && in_array($type, array('hawk1', 'hawk2', 'juicer2', 'rook1', 'spotter1', 'spotter2'))) {
            throw new BgaUserException(clienttranslate('Character action can be used once per turn'));
        } else if($type == 'acrobat2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $plan = new BurgleBrosFloorPlan($this->getSquareSize(), $this->getWalls());
            if ($plan->isInteriorCell($player_tile['location_arg'])) {
                throw new BgaUserException(clienttranslate('Must be on an outer tile'));
            }
            if ($this->stateValue(GameStateValue::ActionsRemaining) < 3) {
                throw new BgaUserException(clienttranslate('Must have at least 3 actions'));
            }
            $this->setStateValue(GameStateValue::CardChoice, $character['id']);
            $this->gamestate->nextState('cardChoice');
        } else if($type == 'hacker2') {   
            if (count($this->tokensOfTypeInLocation(TokenType::Hack, null, 'card', $character['id'])) > 0) {
                throw new BgaUserException(clienttranslate('You already have a hack token'));
            }
            $this->pickTokens(TokenType::Hack, 'card', $character['id']);
            $this->bga->notify->all('message', clienttranslate('${player_name} used their character action'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
            self::incStat(1, 'special_ability_use', $human_player_id);
            $this->endAction();
        } else if($type == 'juicer2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $tile_alarms = $this->tokensOfTypeInLocation(TokenType::Alarm, null, 'tile', $player_tile['id']);
            $character_alarms = $this->tokensOfTypeInLocation(TokenType::Alarm, null, 'card', $character['id']);
            
            if (count($character_alarms) > 0) {
                if (count($tile_alarms) > 0) {
                    throw new BgaUserException(clienttranslate('Tile already has an alarm token'));
                }
                $this->moveToken(array_values($character_alarms)[0]['id'], 'deck');
                if ($this->triggerAlarm($player_tile)) {
                    $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
                    $this->setStateValue(GameStateValue::StateAfterAlarm, State::PlayerTurn);
                    $this->gamestate->nextState('chooseAlarm');
                }
            } else {
                if (count($tile_alarms) == 0) {
                    throw new BgaUserException(clienttranslate('Tile does not have an alarm token'));
                }
                $this->moveToken(array_values($tile_alarms)[0]['id'], 'card', $character['id']);
                if ($this->nextPatrol($this->tileFloor($player_tile))) {
                    $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
                    $this->setStateValue(GameStateValue::StateAfterAlarm, State::PlayerTurn);
                    $this->gamestate->nextState('chooseAlarm');
                }
            }
            $this->setStateValue(GameStateValue::CharacterAbilityUsed, 1);
            self::incStat(1, 'special_ability_use', $human_player_id);
            $this->bga->notify->all('message', clienttranslate('${player_name} used their character action'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
        } else if($type == 'raven2') {
            $crow = array_values($this->tokensOfType(TokenType::Crow))[0];
            $player_tile = $this->getPlayerTile($current_player_id);
            $this->moveToken($crow['id'], 'tile', $player_tile['id']);
            $this->bga->notify->all('message', clienttranslate('${player_name} used their character action'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
            self::incStat(1, 'special_ability_use', $human_player_id);
        } else if($type == 'rigger2') {
            $stealth = $this->getPlayerStealth($current_player_id);
            if ($stealth <= 0) {
                throw new BgaUserException(clienttranslate('You cannot lose any more stealth'));
            }
            $this->decrementPlayerStealth($current_player_id);
            $this->setStateValue(GameStateValue::DrawToolsPlayer, $current_player_id);
            $this->bga->notify->all('message', clienttranslate('${player_name} used their character action'), [
                'player_name' => self::getCurrentPlayerName()
            ]);
            self::incStat(1, 'special_ability_use', $human_player_id);
            $this->endAction(0);
        } else if($type == 'rook1') {
            $this->setStateValue(GameStateValue::PlayerChoice, PlayerChoice::Rook1);
            $this->gamestate->nextState('playerChoice');
        } else if($type == 'rook2') {
            if ($this->stateValue(GameStateValue::FirstAction) != 1) {
                throw new BgaUserException(clienttranslate('You may only use this ability as your first action'));
            }
            $this->setStateValue(GameStateValue::PlayerChoice, PlayerChoice::Rook2);
            $this->gamestate->nextState('playerChoice');
        } else if($type == 'spotter1') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $floor = $this->tileFloor($player_tile);
            $top_card = $this->cards->getCardOnTop(DeckType::patrol($floor)->deckName());
            if (!$top_card) {
                throw new BgaUserException(clienttranslate('Patrol deck is empty'));
            }
            self::incStat(1, 'special_ability_use', $human_player_id);
            $this->setStateValue(GameStateValue::CardChoice, $character['id']);
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $this->gamestate->nextState('cardChoice');
        } else if($type == 'spotter2') {
            $top_card = $this->cards->getCardOnTop(DeckType::Events->deckName());
            if (!$top_card) {
                throw new BgaUserException(clienttranslate('Event deck is empty'));
            }
            self::incStat(1, 'special_ability_use', $human_player_id);
            $this->setStateValue(GameStateValue::CardChoice, $character['id']);
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $this->gamestate->nextState('cardChoice');
        } else if (in_array($type, array('acrobat1', 'hawk1', 'hawk2', 'juicer1', 'peterman2', 'raven1', 'spotter1', 'spotter2'))) {
            $this->setStateValue(GameStateValue::CardChoice, $character['id']);
            $this->gamestate->nextState('cardChoice');
        } else {
            throw new BgaUserException(clienttranslate('Character does not have a special action'));
        }
    }

    function trade() {
        self::checkAction('trade');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $current_player_token = $this->getPlayerToken($current_player_id);
        $player_tokens = $this->tokensOfTypeInLocation(TokenType::Player, null, 'tile', $current_player_token['location_arg']);
        if (count($player_tokens) < 2) {
            throw new BgaUserException(clienttranslate('There are no other players in your tile'));
        }

        if (count($player_tokens) == 2) {
            foreach ($player_tokens as $token) {
                if ($token['type_arg'] != $current_player_id) {
                    $this->createTrade($current_player_id, $token['type_arg']);
                    break;
                }
            }
            $this->gamestate->nextState('proposeTrade');
        } else {
            $this->setStateValue(GameStateValue::PlayerChoice, PlayerChoice::Trade);
            $this->gamestate->nextState('playerChoice');
        }
    }

    function selectPlayerChoice($selected) {
        self::checkAction('selectPlayerChoice');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_choice = $this->stateValue(GameStateValue::PlayerChoice);
        $player_choice_type = $this->player_choices[$player_choice];
        $this->handleSelectPlayerChoice($current_player_id, $player_choice_type, $selected);
    }

    function proposeTrade($p1_ids, $p2_ids) {
        self::checkAction('proposeTrade');
        $trade = $this->getTrade();
        
        $card_ids = array();
        array_merge($card_ids, $p1_ids, $p2_ids);
        foreach ($this->cards->getCards($card_ids) as $id => $card) {
            if (in_array($card['type'], array(CardType::Character->value, CardType::Event->value))) {
                throw new BgaUserException(clienttranslate('Card must be a tool or loot'));
            } elseif ($card['location'] != 'hand' || !in_array($card['location'], array($trade['current_player'], $trade['other_player']))) {
                throw new BgaUserException(clienttranslate('Card does not belong to trading player'));
            }
        }
        // Check only one gold bar max per player
        $gold_type = $this->getCardTypeForName(CardType::Loot,'gold-bar');
        $has_gold_bar = FALSE;
        $player_cards = [$p1_ids, $p2_ids];
        foreach ($player_cards as $p_ids) {
            foreach ($this->cards->getCards($p_ids) as $id => $card) {
                if ($card['type'] == CardType::Loot->value && $card['type_arg'] == $gold_type) {
                    if ($has_gold_bar) {
                        throw new BgaUserException(clienttranslate('One player cannot hold the two gold bars, please propose another trade'));
                    } else {
                        $has_gold_bar = TRUE;
                    }
                }
            }
            $has_gold_bar = FALSE;
        }
        // $players = self::loadPlayersBasicInfos();
        $players = $this->loadPlayersInfos();
        $this->bga->notify->all('message', clienttranslate('${player_name} proposed a trade to ${other_name}'), [
            'player_name' => $players[$trade['current_player']]['player_name'],
            'other_name' => $players[$trade['other_player']]['player_name'],
        ]);

        $this->createTradeCards($trade['id'], $trade['current_player'], $p1_ids);
        $this->createTradeCards($trade['id'], $trade['other_player'], $p2_ids);
        if ($this->getSoloMultiCharacters() > 1) {
            $this->confirmTrade(TRUE);
        } else {
            $this->gamestate->nextState('nextTradePlayer');
        }
    }

    function confirmTrade($force = FALSE) {
        if (!$force) self::checkAction('confirmTrade');
        $trade = $this->getTrade();
        $p1_cards = array_keys($this->getTradeCards($trade['id'], $trade['current_player']));
        $this->cards->moveCards($p1_cards, 'hand', $trade['current_player']);
        $p2_cards = array_keys($this->getTradeCards($trade['id'], $trade['other_player']));
        $this->cards->moveCards($p2_cards, 'hand', $trade['other_player']);
        $this->notifyPlayerHand($trade['current_player'], $p2_cards);
        $this->notifyPlayerHand($trade['other_player'], $p1_cards);
        // $players = self::loadPlayersBasicInfos();
        $players = $this->loadPlayersInfos();
        $this->bga->notify->all('message', clienttranslate('${player_name} agreed to ${current_name}\'s trade'), [
            'player_name' => $players[$trade['other_player']]['player_name'],
            'current_name' => $players[$trade['current_player']]['player_name'],
        ]);
        if ($this->getSoloMultiCharacters() > 1) {
            self::incStat(1, 'trade_confirmed', self::getCurrentPlayerId());
        } else {
            self::incStat(1, 'trade_confirmed', $players[$trade['other_player']]['player_id']);
            self::incStat(1, 'trade_confirmed', $players[$trade['current_player']]['player_id']);            
        }
        $this->gamestate->nextState('endTradeOtherPlayer');
    }

    function cancelPlayerChoice() {
        self::checkAction('cancelPlayerChoice');
        $this->setStateValue(GameStateValue::PlayerChoice, PlayerChoice::None);
        $this->gamestate->nextState('nextAction');
    }

    function cancelTrade() {
        self::checkAction('cancelTrade');
        $stateName = $this->gamestate->getCurrentMainState()->name;
        if ($stateName == 'confirmTrade') {
            $this->bga->notify->all('message', clienttranslate('${player_name} cancelled the trade'), [
                'player_name' => self::getActivePlayerName()
            ]);
            $this->gamestate->nextState('endTradeOtherPlayer');
        } else {
            $this->deleteTrade();
            $this->gamestate->nextState('nextAction');
        }
        $this->setStateValue(GameStateValue::PlayerChoice, PlayerChoice::None);
    }

    function selectSpecialChoice($selected) {
        self::checkAction('selectSpecialChoice');
        $special_choice = $this->stateValue(GameStateValue::SpecialChoice);
        $special_choice_type = $this->special_choices[$special_choice];
        $special_choice_arg = $this->stateValue(GameStateValue::SpecialChoiceArg);
        $this->handleSelectSpecialChoice($special_choice_type, $special_choice_arg, $selected);
    }

    function cancelSpecialChoice() {
        self::checkAction('cancelSpecialChoice');
        $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::None);
        $this->setStateValue(GameStateValue::SpecialChoiceArg, 0);
        $this->gamestate->nextState('nextAction');
    }

    function keepTool($selected) {
        self::checkAction('keepTool');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $tools = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,null, 'choice');
        foreach ($tools as $tool_id => $tool) {
            if ($tool_id == $selected) {
                $drop_loot = $this->stateValue(GameStateValue::DropLoot);
                $draw_tools_player_id = $this->stateValue(GameStateValue::DrawToolsPlayer);
                if ($drop_loot > 0 && $drop_loot == $draw_tools_player_id) {
                    $this->cards->moveCard($selected, 'tile', $drop_loot);
                    $type = $this->getCardType($tool);
                    // Store that donuts was dropped and not used
                    if ($type == "donuts") {
                        $this->setStateValue(GameStateValue::DonutsDropped, 1);
                    }
                    $this->notifyTileCards($drop_loot);
                    $this->setStateValue(GameStateValue::DropLoot, 0);
                } else {
                    $this->cards->moveCard($tool_id, 'hand', $current_player_id);
                    $this->notifyPlayerHand($current_player_id);                    
                }
            } else {
                $this->cards->moveCard($tool_id, DeckType::Tools->discardName());
            }
        }
        $this->setStateValue(GameStateValue::DrawToolsPlayer, 0);
        $draw_tools_next_player = $this->stateValue(GameStateValue::DrawToolsNextPlayer);
        // Save the drawn tools
        $this->undoSavepoint();
        if ($draw_tools_next_player > 0 && $draw_tools_next_player != $current_player_id) {
            $this->gamestate->nextState('drawToolsOtherPlayer');
        } else {
            if ($this->stateValue(GameStateValue::PlayerPass) == 0) {
                $this->gamestate->nextState('nextAction');
            } else {
                $this->gamestate->nextState('endTurn');
            }
        }
    }

    function takeCards() {
        self::checkAction('takeCards');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_tile = $this->getPlayerTile($current_player_id);
        if (TileType::of($player_tile) !== TileType::Safe) {
            throw new BgaUserException(clienttranslate('Cards can only be taken from a safe'));
        }
        $tile_cards = $this->cards->getCardsInLocation('tile', $player_tile['id']);
        if (count($tile_cards) == 0) {
            throw new BgaUserException(clienttranslate('There are no cards in your tile'));
        }

        $this->gamestate->nextState('takeCards');
    }

    function confirmTakeCards($l_ids, $r_ids) {
        self::checkAction('confirmTakeCards');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_tile = $this->getPlayerTile($current_player_id);
        // If player already has the gold bar, they cannot pick the other one
        $hand = $this->cards->getPlayerHand($current_player_id);
        foreach ($hand as $card_id => $card) {
            if ($this->getCardType($card) == 'gold-bar') {
                foreach ($r_ids as $card_id) {
                    $new_card = $this->cards->getCard($card_id);
                    if ($this->getCardType($new_card) == 'gold-bar') {
                        throw new BgaUserException(clienttranslate('You cannot hold the two gold bars, another player has to pick it up'));
                    }
                }        
                break;
            }
        }
        // If drawn card is the cursed goblet, player loses one stealth
        foreach ($r_ids as $card_id) {
            $new_card = $this->cards->getCard($card_id);
            if ($this->getCardType($new_card) == 'cursed-goblet') {
                $stealth = $this->getPlayerStealth($current_player_id);
                if ($stealth > 0) {
                    $this->decrementPlayerStealth($current_player_id);
                }
            }
        }
        // If picked up cards is the Donuts, reset global variable so it can be used
        if ($this->getCardType($new_card) == 'donuts') {
            $this->setStateValue(GameStateValue::DonutsDropped, 0);
        }
        $this->cards->moveCards($l_ids, 'tile', $player_tile['id']);
        $this->cards->moveCards($r_ids, 'hand', $current_player_id);
        $this->notifyPlayerHand($current_player_id);
        $this->notifyTileCards($player_tile['id']);
        $this->bga->notify->all('message', clienttranslate('${player_name} picked up ${card_count} cards in their tile'), [
            'player_name' => $this->getActivePlayerNameCustom(),
            'card_count' => count($r_ids)
        ]);
        $this->endAction(0);
    }

    function cancelTakeCards() {
        self::checkAction('cancelTakeCards');
        $this->gamestate->nextState('nextAction');
    }

    function confirmRookMove() {
        // Player confirmed the Rook movement
        $this->setStateValue(GameStateValue::CharacterAbilityUsed, 1);
        if ($this->getSoloMultiCharacters() > 1) {
            $current_player_id =  $this->stateValue(GameStateValue::CurrentPlayer);
            $rook_player_id = self::getCurrentPlayerId();
        } else {
            $current_player_id =  self::getCurrentPlayerId();
            $this->bga->notify->all('message', clienttranslate('${player_name} accepts the move'), [
                'player_name' => self::getActivePlayerName(),
            ]);
            $rook_player_id = $this->stateValue(GameStateValue::CurrentPlayer);
        }
        self::incStat(1, 'special_ability_use', $rook_player_id);

        $choice_arg = $this->stateValue(GameStateValue::SpecialChoiceArg);
        $selected = $this->stateValue(GameStateValue::RookDestinationTile);
        $player_token = $this->getPlayerToken($choice_arg);
        $move_result = $this->performMove($selected, 'rook1', $choice_arg);
        if ($this->stateValue(GameStateValue::StealthDepleted)) {
            $this->gamestate->nextState('gameOver');
        } else if ($move_result['tile_choice']) {
            $this->setStateValue(GameStateValue::TileChoice, $move_result['tile_choice']);
            $this->gamestate->nextState('tileChoice');
        } else {
            $this->setStateValue(GameStateValue::SpecialChoiceArg, 0);
            $this->setStateValue(GameStateValue::RookDestinationTile, 0);
            $this->gamestate->nextState('switchRookMove');
        }
    }

    function cancelRookMove() {
        self::checkAction('cancelRookMove');
        $this->bga->notify->all('message', clienttranslate('${player_name} cancels the move'), [
            'player_name' => self::getActivePlayerName(),
        ]);
        $this->setStateValue(GameStateValue::RookDestinationTile, -1);
        $this->setStateValue(GameStateValue::SpecialChoiceArg, 0);
        $this->gamestate->nextState('switchRookMove');
    }

    function pickUpCat() {
        self::checkAction('pickUpCat');
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_tile = $this->getPlayerTile($current_player_id);
        $cat_tokens = $this->tokensOfTypeInLocation(TokenType::Cat, null, 'tile', $player_tile['id']);
        if (count($cat_tokens) == 0) {
            throw new BgaUserException(clienttranslate('Tile does not contain the cat token'));
        }
        $this->moveTokens(array_keys($cat_tokens), 'deck');
        $kitty_card = $this->getLootOwner('persian-kitty');
        $kitty_owner = $kitty_card['location_arg'];
        // If another player picked up the Persian Kitty, move the loot card to the new owner
        if ($kitty_owner != $current_player_id) {
            $this->cards->moveCard($kitty_card['id'], 'hand', $current_player_id);
            $this->notifyPlayerHand($current_player_id);
            $this->notifyPlayerHand($kitty_owner, array($kitty_card['id']));
        }
        $this->bga->notify->all('catPicked', clienttranslate('${player_name} picks up the cat token'), [
            'player_name' => $this->getActivePlayerNameCustom()
        ]);
        $this->endAction(0);
    }

    function pass() {
        self::checkAction('pass');
        $this->setStateValue(GameStateValue::PlayerPass, 1);
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        $trigger_action_count = $this->getPlayerLoot( 'stamp', $current_player_id) ? 1 : 2;
        if ($actions_remaining >= $trigger_action_count) {
            $count = $this->cards->countCardInLocation(DeckType::Events->discardName());
            $event_card = $this->cards->pickCardForLocation(DeckType::Events->deckName(), DeckType::Events->discardName(), $count + 1);
            // $type_arg = $this->getCardTypeForName(CardType::Event,'buddy-system');
            // $event_card = array_values($this->cards->getCardsOfType(CardType::Event->value,$type_arg))[0];
            self::incStat(1, 'event_cards');
            if ($event_card) {
                $this->bga->notify->all('eventCard', clienttranslate('Event Card: ${title} (${tooltip})'), array(
                    'card_id' => $event_card['id'],
                    'card' => $event_card,
                    'title' => $this->getCardTitle($event_card),
                    'tooltip' => $this->getCardTooltip($event_card)
                ));
                $event_result = $this->handleEventEffect($current_player_id, $event_card);
            } else {
                $event_result = array('card_choice'=>FALSE,'tile_choice'=>FALSE);
            }
            
            if ($this->stateValue(GameStateValue::StealthDepleted)) {
                $this->gamestate->nextState('gameOver');
            } elseif ($event_result['card_choice']) {
                $this->setStateValue(GameStateValue::CardChoice, $event_card['id']);
                $this->gamestate->nextState('cardChoice');
            } elseif ($event_result['tile_choice']) {
                $this->setStateValue(GameStateValue::TileChoice, $event_result['tile_choice']);
                $this->gamestate->nextState('tileChoice');
            } elseif ($event_result['player_choice']) {
                $this->setStateValue(GameStateValue::PlayerChoice, $event_result['player_choice']);
                $this->gamestate->nextState('playerChoice');
            } elseif ($event_result['special_choice']) {
                $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
                $this->setStateValue(GameStateValue::StateAfterAlarm, State::MoveGuard);
                $this->gamestate->nextState('chooseAlarm');
            } elseif ($this->stateValue(GameStateValue::DrawToolsPlayer) > 0) {
                $this->gamestate->nextState('endAction');
            } else {
                $this->gamestate->nextState('endTurn');
            }
        } else {
            $this->gamestate->nextState('endTurn');
        }
    }

    function restartTurn() {
        // Restart turn by restoring save point
        self::checkAction('restartTurn');
        if ($this->stateValue(GameStateValue::UndoAllowed) == 0)
            throw new BgaUserException(clienttranslate("You are not allowed to restart your turn (you uncovered some info or triggered some random events)"));
        $this->undoRestorePoint();
        $this->gamestate->nextState('restartTurn');
    }

    function escape() {
        self::checkAction('escape');
        $actions_remaining = $this->stateValue(GameStateValue::ActionsRemaining);
        if ($actions_remaining < 1) {
            throw new BgaUserException(clienttranslate("You have no actions remaining"));
        }

        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        if (!$this->checkWin()) {
            throw new BgaUserException(clienttranslate('You must open all the safes and get all the loots before you escape (especially the Persian Kitty)'));
        }
        $this->moveToken($player_token['id'], 'roof');
        $this->bga->notify->all('playerEscape', clienttranslate('${player_name} escapes to the roof'), [
            'player_id' => $current_player_id,
            'player_name' => $this->getActivePlayerNameCustom(),
            'token_id' => $player_token['id'],
        ]);

        if ($this->allPlayersEscaped()) {
            if ($this->checkWin()) {
                $this->bga->playerScore->setAll(1, null);
            }
            $this->gamestate->nextState('gameOver');
        } else {
            $this->resetGlobalVars();
            if ($this->stateValue(GameStateValue::EmpPlayer) == $current_player_id) {
                $this->setStateValue(GameStateValue::EmpPlayer, 0);
            }
            $this->gamestate->nextState('nextPlayer');
        }
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argChooseCharacter() {
        // Return basic (and if relevant advanced side) of each player character
        $cards = $this->getAvailableCharacters();
        $player_id = $this->getSoloMultiCharacters() > 1 ? $this->getCurrentPlayerIdCustom() : 0;
        return array(
            'cards' => $cards,
            'floor' => 1,
            'player_id' => $player_id,
        );
    }

    function argStartingTile() {
        return array(
            'floor' => 1
        );
    }

    function argPlayerTurn() {
        // Can't get current player here for some reason
        return $this->gatherCurrentData($this->getActivePlayerIdCustom());
    }

    function argConfirmRookMove() {
        $destination_id = $this->stateValue(GameStateValue::RookDestinationTile);
        if ($destination_id > 0) {
            $tile = $this->tiles->getCard($destination_id);
            $patrol_names = $this->patrolNames();
            $destination_name = $patrol_names[$tile['location_arg']]['name'];
            return array(
                'destination_id' => $destination_id,
                'destination_name' => $destination_name,
                'floor' => $this->tileFloor($tile),
                'undo_allowed' => 0,
            );
        } else {
            return array();
        }
    }

    function argCardChoice() {
        $current_player_id = $this->getActivePlayerIdCustom();
        $args = $this->gatherCurrentData($current_player_id);
        $card = $this->cards->getCard($this->stateValue(GameStateValue::CardChoice));
        $card_name = $this->getCardType($card);
        $args['card'] = $card;
        $args['card_name'] = $card_name;
        $args['card_name_displayed'] = $this->getDisplayedCardName($card_name);
        $args['choice_description'] = $this->getCardChoiceDescription($card);
        $args['i18n'] = ['choice_description'];
        if ($card_name == 'peterman2') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $peterman2_detail = [];
            $max_floor = $this->getFloorCount();
            for ($floor=1; $floor <= $max_floor; $floor++) {
                if (abs($floor - $this->tileFloor($player_tile)) == 1) {   
                    $peterman2_detail[$floor] = FALSE;
                    $tiles = $this->getTiles($floor);
                    foreach ($tiles as $tile) {
                        if (TileType::of($tile) === TileType::Safe && $tile['location_arg'] == $player_tile['location_arg'] && !$this->tokensInTile(TokenType::Open, $tile['id'])) {
                            $peterman2_detail[$floor] = TRUE;
                        }
                    }
                }                
            }
            $args['peterman2_detail'] = $peterman2_detail;
        } else if ($card_name == 'spotter1') {
            $player_tile = $this->getPlayerTile($current_player_id);
            $floor = $this->tileFloor($player_tile);
            $args['spotter_card'] = $this->cards->getCardOnTop(DeckType::patrol($floor)->deckName());
        } else if ($card_name == 'spotter2') {
            $args['spotter_card'] = $this->cards->getCardOnTop(DeckType::Events->deckName());
        } else if ($card_name == 'crystal-ball') {
            $args['event_cards'] = $this->cards->getCardsOnTop(3, DeckType::Events->deckName());
        } else if ($card_name == 'stethoscope') {
            $args['rolls'] = $this->tokens->getCardsInLocation("stethoscope");
        }
        return $args;
    }

    function argTileChoice() {
        $player_id = $this->getActivePlayerIdCustom();
        $player_token = $this->getPlayerToken($player_id);
        $tile = $this->tiles->getCard($this->stateValue(GameStateValue::TileChoice));
        $args = array(
            'escape' => false,
            'peekable' => array(),
            'player_token' => $player_token,
            'tile' => $tile,
            'floor' => $this->tileFloor($tile),
            'actions_remaining' => $this->stateValue(GameStateValue::ActionsRemaining)
        );
        $args['tile_name'] = $tile['type'];
        $args['can_hack'] = $this->canHack($tile);
        $args['can_use_extra_action'] = $this->canUseExtraAction($player_id, $tile);

        return $args;
    }

    function argPlayerChoice() {
        $args = $this->gatherCurrentData($this->getActivePlayerIdCustom());
        $player_choice = $this->stateValue(GameStateValue::PlayerChoice);
        $args['context'] = $this->player_choices[$player_choice];
        return $args;
    }

    function argProposeTrade() {
        $args = $this->gatherCurrentData($this->getActivePlayerIdCustom());
        $args['trade'] = $this->getTrade();
        return $args;
    }

    function argConfirmTrade() {
        $args = $this->gatherCurrentData($this->getActivePlayerIdCustom());
        $trade = $this->getTrade();
        $args['trade'] = $trade;
        $args['p1_cards'] = $this->getTradeCards($trade['id'], $trade['other_player']);
        $args['p2_cards'] = $this->getTradeCards($trade['id'], $trade['current_player']);
        return $args;
    }

    function argSpecialChoice() {
        $args = $this->gatherCurrentData($this->getActivePlayerIdCustom());
        $special_choice = $this->stateValue(GameStateValue::SpecialChoice);
        $choice_arg = $this->stateValue(GameStateValue::SpecialChoiceArg);
        $card = $this->cards->getCard($this->stateValue(GameStateValue::CardChoice));
        $type = $this->special_choices[$special_choice];
        $args['i18n'] = ['choice_name', 'choice_description'];
        $args['show_cancel'] = TRUE;
        if ($type == 'rook1') {
            $args['choice_name'] = clienttranslate('Orders');
            $args['choice_description'] = clienttranslate('an adjacent tile to move the player');
        } elseif ($type == 'closest_alarm') {
            $args['choice_name'] = clienttranslate('Guard move');
            $args['choice_description'] = clienttranslate('one of the closest alarm to set the Guard destination');
            $args['show_cancel'] = FALSE;
        }
        return $args;
    }

    function argDrawToolsAndDiscard() {
        $args = $this->gatherCurrentData($this->getActivePlayerIdCustom());
        $args['tools'] = $this->cards->getCardsOfTypeInLocation(CardType::Tool->value,null, 'choice');
        return $args;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */
    
    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */
    function stRandomizeWalls() {
        // Move on if the game use default walls
        if ($this->bga->tableOptions->get(GameOption::Walls->value) == Walls::Default->value) {
            $this->gamestate->nextState('');
        }
    }

    function stChooseCharacter() {
        // Move on if the game only use basic characters
        if ($this->bga->tableOptions->get(GameOption::CharacterAssignment->value) == CharacterAssignment::Random->value) {
            $this->gamestate->nextState('chooseCharacter');
        }
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stEndAction() {
        $current_player_id = $this->getActivePlayerIdCustom();
        $players = $this->loadPlayersInfos();
        $draw_tools_player_id = $this->stateValue(GameStateValue::DrawToolsPlayer);
        $draw_two = $this->showRiggerToolSelection();
        $next_state = $this->stateValue(GameStateValue::PlayerPass) == 1 ? 'endTurn' : 'nextAction';
        $drop_loot = $this->stateValue(GameStateValue::DropLoot);
        if ($draw_tools_player_id == 0) {
            $this->gamestate->nextState($next_state);
        } else if ($draw_tools_player_id != 0 && !$draw_two) {
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $this->setStateValue(GameStateValue::DrawToolsPlayer, 0);
            $this->reshuffleDeckIfEmpty(DeckType::Tools);
            
            // Check if tool should be dropped on the tile
            if ($drop_loot > 0 && $drop_loot == $draw_tools_player_id) {
                $safe_tile_id = $draw_tools_player_id;
                $tool = $this->cards->pickCardForLocation(DeckType::Tools->deckName(), 'tile', $safe_tile_id);
                $type = $this->getCardType($tool);
                // Store that donuts was dropped and not used
                if ($type == "donuts") {
                    $this->setStateValue(GameStateValue::DonutsDropped, 1);
                }
                $this->setStateValue(GameStateValue::DropLoot, 0);
                $this->notifyTileCards($safe_tile_id);
            } else {
                $card = $this->cards->pickCard(DeckType::Tools->deckName(), $draw_tools_player_id);
                $card_name = $this->getCardType($card);
                $human_player_id = $this->getActivePlayerId();
                self::incStat( 1, 'tools_drawn', $human_player_id );
                $this->bga->notify->all('addTooltipToLog', clienttranslate('${player_name} draws ${title} (${tooltip})'), [
                    'i18n' => ['title', 'tooltip'],
                    'card_id' => $card['id'],
                    'card' => $card,
                    'player_name' => isset($players[$draw_tools_player_id]) ? $players[$draw_tools_player_id]['player_name'] : $players[$current_player_id]['player_name'],
                    'title' => $this->getDisplayedCardName($card_name),
                    'tooltip' => $this->getCardTooltip($card),
                ]);
                $this->notifyPlayerHand($draw_tools_player_id);
            }
            $this->undoSavepoint();
            $this->setStateValue(GameStateValue::UndoAllowed, 2);
            $this->gamestate->nextState($next_state);
        } else {
            $this->setStateValue(GameStateValue::UndoAllowed, 0);
            $human_player_id = $this->getActivePlayerId();
            self::incStat( 1, 'tools_drawn', $human_player_id );
            if ($draw_tools_player_id != $current_player_id) {
                $this->setStateValue(GameStateValue::DrawToolsNextPlayer, $current_player_id);
                // var_dump($drop_loot);
                // var_dump($draw_tools_player_id);
                if ($drop_loot == 0)
                    $this->gamestate->changeActivePlayer($draw_tools_player_id);
            }
            $this->reshuffleDeckIfEmpty(DeckType::Tools);
            $this->cards->pickCardForLocation(DeckType::Tools->deckName(), 'choice');
            $this->reshuffleDeckIfEmpty(DeckType::Tools);
            $this->cards->pickCardForLocation(DeckType::Tools->deckName(), 'choice');
            $this->undoSavepoint();
            $this->setStateValue(GameStateValue::UndoAllowed, 2);
            $this->gamestate->nextState('drawTools');
        }
        $this->setStateValue(GameStateValue::FirstAction, 0);
    }

    function stEndTurn() {
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        $special_choice = FALSE;
        if (TileType::of($player_tile) === TileType::Thermo && $this->stateValue(GameStateValue::EmpPlayer) == 0 && !$this->tokensInTile(TokenType::Crowbar, $player_tile['id'])) {
            $special_choice = $this->triggerAlarm($player_tile);
        }
        if ($this->stateValue(GameStateValue::AcrobatEnteredGuardTile)) {
            $this->deductTileStealth($player_tile['id'], 'acrobat');
            // $this->decrementPlayerStealth($current_player_id);
        }
        $this->resetGlobalVars();
        $this->bga->notify->all('message', clienttranslate('${player_name} ended their turn'), [
            'player_name' => $this->getActivePlayerNameCustom()
        ]);
        if ($special_choice) {
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
            $this->setStateValue(GameStateValue::StateAfterAlarm, State::MoveGuard);
            $this->gamestate->nextState( 'chooseAlarm' );
        } else {
            $this->gamestate->nextState( 'moveGuard' );
        }
    }

    function stMoveGuard() {
        $current_player_id = $this->getCurrentPlayerIdCustom();
        $player_token = $this->getPlayerToken($current_player_id);
        $player_tile = $this->getPlayerTile($current_player_id, $player_token);
        $floor = $this->tileFloor($player_tile);
        $choose_alarm = FALSE;
        $shift_change = $this->getActiveEvent('shift-change');
        if ($shift_change) {
            $max_floor = $this->getFloorCount();
            for ($other_floor=1; $other_floor <= $max_floor; $other_floor++) { 
                if ($other_floor != $floor) {
                    $guard_token = array_values($this->tokensOfType(TokenType::Guard, $other_floor))[0];;
                    if ($guard_token['location'] == 'tile') {
                        $alarms = count($this->getFloorAlarmTiles($other_floor));
                        $movement = $this->stateValue(GameStateValue::patrolDieCount($other_floor)) + $alarms;
                        $this->notifyGuardMovement($other_floor, $movement, $alarms, TRUE);
                        $choose_alarm = $this->moveGuard($other_floor, $movement);
                    }
                }
            }
            $this->cards->moveCard($shift_change['id'], DeckType::Events->discardName());
            $this->notifyPlayerHand($current_player_id, array($shift_change['id']));
        } else {
            $guard_token = array_values($this->tokensOfType(TokenType::Guard, $floor))[0];
            $guard_tile = $this->tiles->getCard($guard_token['location_arg']);
            if ($this->tokensInTile(TokenType::Crow, $guard_tile['id']) &&
                $this->getPlayerCharacter(null, 'raven2') &&
                count($this->getFloorAlarmTiles($floor)) == 0) {
                $crow = array_values($this->tokensOfType(TokenType::Crow))[0];
                $this->moveToken($crow['id'], 'deck');
            } else {
                $alarms = count($this->getFloorAlarmTiles($floor));
                $movement = $this->stateValue(GameStateValue::patrolDieCount($floor)) + $alarms;
                $has_event = FALSE;
                $daydreaming = $this->getActiveEvent('daydreaming');
                if ($daydreaming) {
                    $movement--;
                    $this->cards->moveCard($daydreaming['id'], DeckType::Events->discardName());
                    $this->notifyPlayerHand($current_player_id, array($daydreaming['id']));
                    $has_event = TRUE;
                }
                $espresso = $this->getActiveEvent('espresso');
                if ($espresso) {
                    $movement++;
                    $this->cards->moveCard($espresso['id'], DeckType::Events->discardName());
                    $this->notifyPlayerHand($current_player_id, array($espresso['id']));
                    $has_event = TRUE;
                }
                $this->notifyGuardMovement($floor, $movement, $alarms, $has_event);
                $choose_alarm = $this->moveGuard($floor, $movement);
            }
        }
        if ($this->stateValue(GameStateValue::StealthDepleted)) {
            $this->gamestate->nextState('gameOver');
        } elseif ($choose_alarm) {
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
            $this->setStateValue(GameStateValue::StateAfterAlarm, State::MoveGuard);
            $this->gamestate->nextState('chooseAlarm');
        } else {
            $this->gamestate->nextState( 'nextPlayer' );
        }
    }

    function stNextPlayer() {
        $human_player_id = self::getCurrentPlayerId();
        $player_id = $this->activeNextPlayerCustom();
        $jump_the_gun = $this->getActiveEvent('jump-the-gun');
        $special_choice = FALSE;
        if ($jump_the_gun) {
            $players = $this->loadPlayersInfos();
            $player_id = $this->skipEscapedPlayers($player_id);
            $this->bga->notify->all('message', clienttranslate( 'Skipped ${player_name}\'s turn' ), array(
                'player_name' => $players[$player_id]['player_name'],
            ));
            $this->cards->moveCard($jump_the_gun['id'], DeckType::Events->discardName());
            $this->notifyPlayerHand($player_id, array($jump_the_gun['id']));
            $player_id = $this->activeNextPlayerCustom();
        }
        $player_id = $this->skipEscapedPlayers($player_id);
        self::giveExtraTime( $human_player_id );
        $heads_up = $this->getActiveEvent('heads-up');
        $actions = $heads_up ? 5 : 4;
        if ($this->getPlayerLoot('mirror', $player_id)) {
            $actions--;
        }
        $this->setStateValue(GameStateValue::ActionsRemaining, $actions);
        $this->setStateValue(GameStateValue::MotionTileEntered, 0x000);
        $hand = $this->tokens->getPlayerHand($player_id);
        $token = array_shift($hand);
        if ($token) {
            $entrance = $this->stateValue(GameStateValue::EntranceTile);
            $this->moveToken($token['id'], 'tile', $entrance);
        }
        $emp_player = $this->stateValue(GameStateValue::EmpPlayer);
        if ($emp_player == $player_id) {
            $this->setStateValue(GameStateValue::EmpPlayer, 0);
        }
        // Cleanup round events for a player
        $round_events = array_keys($this->cards->getCardsOfTypeInLocation(CardType::Event->value,null, 'hand', $player_id));
        if (count($round_events) > 0) {
            $this->cards->moveCards($round_events, DeckType::Events->discardName());
            $this->notifyPlayerHand($player_id, $round_events);
        }
        $this->clearTileTokens(TokenType::Keypad);

        $chihuahua = $this->getPlayerLoot('chihuahua', $player_id);
        if ($chihuahua) {
            $rolls = $this->rollDice(1);
            $this->notifyRoll($rolls, 'chihuahua');
            if (isset($rolls[6])) {
                $tile = $this->getPlayerTile($player_id);
                $special_choice = $this->triggerAlarm($tile);
            }
        }

        $persian_kitty = $this->getPlayerLoot('persian-kitty', $player_id);
        if ($persian_kitty) {
            if (count($this->getPlacedTokens(array(TokenType::Cat))) == 0) {
                $rolls = $this->rollDice(1);
                $this->notifyRoll($rolls, 'persian-kitty');
                if (isset($rolls[1]) || isset($rolls[2])) {
                    $this->moveCatToken($player_id);
                    $this->bga->notify->all('catEscaped', clienttranslate('${player_name} must pick up the cat token before escaping'), [
                        'player_name' => $this->getActivePlayerNameCustom()
                    ]);
                }
            }
        }

        $player_tile = $this->getPlayerTile($player_id);
        $this->bga->notify->all('showFloor', '', [
            'floor' => $this->tileFloor($player_tile),
            'delay' => true,
        ]);

        self::incStat(1, 'turns_number');
        self::incStat(1, 'turns_number', $human_player_id);

        $this->undoSavepoint();
        $this->setStateValue(GameStateValue::UndoAllowed, 1);

        if ($special_choice) {
            $this->setStateValue(GameStateValue::SpecialChoice, SpecialChoice::ClosestAlarm);
            $this->setStateValue(GameStateValue::StateAfterAlarm, State::PlayerTurn);
            $this->gamestate->nextState( 'chooseAlarm' );
        } else {
            $this->gamestate->nextState( 'playerTurn' );
        }
    }

    function stNextTradePlayer() {
        $trade = $this->getTrade();
        $this->gamestate->changeActivePlayer( $trade['other_player'] );
        $this->gamestate->nextState( 'confirmTrade' );
    }

    function stEndTradeOtherPlayer() {
        $trade = $this->getTrade();
        if ($this->getSoloMultiCharacters() <= 1) {
            $this->gamestate->changeActivePlayer( $trade['current_player'] );
        }
        $this->deleteTrade();
        $this->gamestate->nextState( 'nextAction' );
    }

    function stSwitchRookMove() {
        // Switch active player between Rook and chosen player who must accept move / draw tools if laboratory...
        $destination_tile = $this->stateValue(GameStateValue::RookDestinationTile);
        $multi_characters = $this->getSoloMultiCharacters();
        if ($destination_tile > 0) {
            if ($multi_characters > 1) {
                $this->confirmRookMove();
            } else {
                // Activate other player
                $player_id = $this->stateValue(GameStateValue::SpecialChoiceArg);
                $this->gamestate->changeActivePlayer( $player_id );
                $this->gamestate->nextState( 'confirmRookMove' );
            }
        } else { // Activate Rook
            if ($multi_characters <= 1) {
                $player_id = $this->stateValue(GameStateValue::CurrentPlayer);
                $this->gamestate->changeActivePlayer( $player_id );
            }
            if ($destination_tile == -1) { // other player cancelled the move
                $this->setStateValue(GameStateValue::RookDestinationTile, 0);
                $this->endAction(0);
            } else {
                $this->endAction();
            }
        }
    }

    function stDrawToolsOtherPlayer() {
        $this->gamestate->changeActivePlayer( $this->stateValue(GameStateValue::DrawToolsNextPlayer) );
        $this->setStateValue(GameStateValue::DrawToolsNextPlayer, 0);
        $this->gamestate->nextState( 'nextAction' );
    }

    function stGameOver() {
        $sql = "SELECT player_id id, player_score score, player_stealth_tokens stealth_tokens FROM player ";
        $players = self::getCollectionFromDb( $sql );
        foreach ($players as $player_id => $player) {
            self::setStat( $player['stealth_tokens'], 'stealth_remaining', $player_id );
        }
        // Show unflipped tiles
        $tiles_unflipped = self::getCollectionFromDB("SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM tile WHERE flipped=0 AND NOT card_location IN ('deck','oop')");
        self::setStat( count($tiles_unflipped), 'tiles_unflipped' );
        foreach ($tiles_unflipped as $tile_id => $tile) {
            $this->bga->notify->all('tileFlipped', '', array(
                'tile' => $tile,
                'floor' => $this->tileFloor($tile),
                'undo_allowed' => 0,
                'flipped_tiles' => $this->getFlippedTiles(),
            ));
        }
        $this->gamestate->nextState( 'endGame' );   
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new VisibleSystemException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
