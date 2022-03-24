/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * burglebros implementation : © Brian Gregg baritonehands@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * burglebros.js
 *
 * burglebros user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone"
],
function (dojo, declare) {
    return declare("bgagame.burglebros", ebg.core.gamegui, {
        constructor: function(){
            console.log('burglebros constructor');
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
            this.cardwidth = 120;
            this.cardheight = 120;
            this.nonGenericTokenTypes = ['player', 'guard', 'patrol', 'crack'];
            this.diceColors = { // green, red, grey, yello, blue, purple
                'safe' : 'green',
                'stethoscope' : 'grey',
                'guard' : 'red',
                'debug' : 'red',
                'keypad' : 'yellow',
                'chihuahua' : 'purple',
                'persian-kitty' : 'blue'
            };
            this.diceRolls = 0;
            // Guard SVG path
            this.path_x_offset = 17;
            this.path_y_offset = 17;
            this.path_tile_offset = 29;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup", "gamedatas: ", gamedatas );
            
            // Set up your game interface here, according to "gamedatas"
            window.gamedatas = gamedatas;
            window.app = this;

            this.zones = {};

            // If solo multi-characters options, copy overall player board to instanciate virtual player boards
            if ( gamedatas.solo_characters > 1 ) {
                var active_player_id = $('player_boards').firstElementChild.id.split('_').slice(-1).pop();
                for (var char_index = 1; char_index <= gamedatas.solo_characters - 1; char_index++) {
                    var player_id = parseInt(active_player_id) + char_index;
                    var player_board = $('player_boards').firstElementChild.cloneNode(true);
                    if (!$('overall_player_board_' + player_id)) {
                        player_board.id = 'overall_player_board_' + player_id;
                        var child_nodes = player_board.getElementsByTagName("*");
                        for (i = 0; i < child_nodes.length; i++) {
                            var node = child_nodes[i];
                            node.id = node.id.replace(active_player_id, player_id);
                            if (node.tagName === 'IMG') {
                                node.src = '';
                            } else if (node.tagName === 'A') {
                                node.href = '#player_board_' + player_id;
                                node.innerHTML = gamedatas.players[player_id]['player_name'];
                                node.target = '';
                                node.style.color = '#' + gamedatas.players[player_id]['player_color'];
                            }
                        }
                        dojo.place(player_board, $('player_boards'), 'last');
                    }
                }
            }
            // Setting up player boards
            this.playerHands = {};
            for (var playerId in gamedatas.players) {
                var hand, handDivId, me = false;
                if (playerId == this.player_id) {
                    this.myHand = new ebg.stock();
                    hand = this.myHand;
                    handDivId = 'myhand';
                    me = true;
                } else {
                    this.playerHands[playerId] = new ebg.stock();
                    hand = this.playerHands[playerId];
                    handDivId = 'player_hand_' + playerId.toString();
                }
                
                hand.create( this, $(handDivId), this.cardwidth, this.cardheight);
                hand.image_items_per_row = 2;
                hand.onItemCreate = dojo.hitch(this, 'createCardZone', hand);
                hand.centerItems = true;
                if (me || gamedatas.solo_characters > 1) {
                    hand.setSelectionMode(1);
                    hand.setSelectionAppearance('class');
                    dojo.connect( hand, 'onChangeSelection', this, 'handleCardSelected');
                } else {
                    hand.setSelectionMode(0);
                }

                this.addCardTypesToStock(hand, [0, 1, 2, 3]);

                var player = gamedatas.players[playerId];
                var cards = player.hand;
                this.loadPlayerHand(hand, cards, [], false);
                this.createPlayerBoard(playerId);
                // If player escaped, change playerboard
                if (gamedatas.players[playerId]['escaped'])
                    this.playerEscaped(playerId);
                // Bind actions to player board
                dojo.connect( $('player_' + playerId + '_geolocate'), 'onclick', dojo.hitch(this, 'showPlayer', playerId));
                this.addTooltip('player_' + playerId + '_geolocate', '', _("Click to find the player meeple on the board"));
                if (playerId == this.player_id) {
                    dojo.removeClass('player_' + playerId + '_distribution', 'hidden');
                    dojo.connect( $('player_' + playerId + '_distribution'), 'onclick', dojo.hitch(this, 'showDistribution', playerId));
                    this.addTooltip('player_' + playerId + '_distribution', '', _("Click to show the current discovered card distribution"));
                }
            }

            // Setup tiles and patrols
            if (gamedatas.square_size == 5) {
                dojo.addClass('board_wrap', 'size_5x5');
            }
            this.patrolCounters = {};
            for(var floor = 1; floor <= gamedatas.floor_count; floor++) {
                var key = 'floor' + floor;
                for ( var tileId in this.gamedatas[key]) {
                    var tile = this.gamedatas[key][tileId];
                    this.createTileContainer(floor, tile);
                    this.playTileOnTable(floor, tile);
                }

                // For 4-card squares, use the Stock Patrol cards
                var patrolKey = 'patrol' + floor;
                if (gamedatas.square_size == 4) {
                    this[patrolKey] = new ebg.stock();
                    this[patrolKey].create(this, $(patrolKey), this.cardwidth, this.cardheight);
                    this[patrolKey].image_items_per_row = gamedatas.square_size;
                    this[patrolKey].setSelectionMode(0);

                    for (var type in gamedatas.patrol_types) {
                        var typeInfo = gamedatas.patrol_types[type];
                        for (var index = 0; index < typeInfo.cards.length; index++) {
                            var cardInfo = typeInfo.cards[index];
                            var id = ((cardInfo.type - 4) * 16) + index;
                            this[patrolKey].addItemType(id, id, g_gamethemeurl + 'img/patrol.jpg', id);
                        }
                    }
                    // Patrol back
                    this[patrolKey].addItemType(51, 51, g_gamethemeurl + 'img/patrol.jpg', 51);
                } else {
                    // Create custom Patrol cards
                    var tiles_count = gamedatas.square_size * gamedatas.square_size;
                    for (var i = 0; i <= tiles_count - 1; i++) {
                        var id = patrolKey + this.gamedatas.patrol_names[i].name; // floor1A1...
                        dojo.place(this.format_block('jstpl_patrol_tile', {
                            id : id,
                        }), patrolKey);
                        if (i == gamedatas.shaft_position) {
                            dojo.addClass(id,'shaft');
                        }
                    }
                    dojo.addClass('patrol_wrapper' + floor, 'patrol_card_back');
                    dojo.addClass(patrolKey, 'hidden');
                }

                var patrolTopKey = patrolKey + '_discard_top';
                this.loadPatrolDiscard(floor, gamedatas[patrolTopKey]);

                // Deck counter
                this.patrolCounters[floor] = new ebg.counter();
                this.patrolCounters[floor].create('patrol_counter' + floor);
                this.patrolCounters[floor].toValue(gamedatas.patrol_counters[floor]);
                this.addTooltip('patrol_counter' + floor, dojo.string.substitute(_("Remaining patrol cards on floor ${floor}"), {floor: floor}), '');

                dojo.connect( $('floor' + floor.toString() + '_preview'), 'onclick', dojo.hitch(this, 'showFloor', floor));

                // Guard path
                this.createGuardPath(floor, gamedatas.guard_paths['floor' + floor]);
            }

            for (var wallIdx = 0; wallIdx < gamedatas.walls.length; wallIdx++) {
                var wall = gamedatas.walls[wallIdx];
                this.playWallOnTable(wall);
            }

            for (var token_id in gamedatas.player_tokens) {
                var token = gamedatas.player_tokens[token_id];
                if (token.location != 'hand') {
                    this.createPlayerToken(token_id, token.type_arg);
                    if (this.canMoveToken(token)) {
                        this.moveToken('player', token);
                    }                    
                }
            }

            for (var token_id in gamedatas.guard_tokens) {
                var token = gamedatas.guard_tokens[token_id];
                this.createGuardToken(token_id);
                if (this.canMoveToken(token)) {
                    this.moveToken('guard', token);
                }
            }

            for (var token_id in gamedatas.patrol_tokens) {
                var token = gamedatas.patrol_tokens[token_id];
                this.createPatrolToken(token, token.die_num);
            }

            for (var token_id in gamedatas.crack_tokens) {
                var token = gamedatas.crack_tokens[token_id];
                this.createSafeToken(token, token.die_num);
            }

            for (var token_id in gamedatas.generic_tokens) {
                var token = gamedatas.generic_tokens[token_id];
                this.createGenericToken(token);
                if (this.canMoveToken(token)) {
                    this.moveToken('generic', token);
                }
            }

            for (var card_id in gamedatas.card_tokens) {
                var token = gamedatas.card_tokens[card_id];
                this.createCardToken(card_id, token.type, token.count, '');
            }

            this.showFloor(this.currentFloor());

            if (gamedatas.solo_characters > 1) {
                this.activatePlayer(gamedatas.active_player_id);
            }
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName, args.args );
            
            switch( stateName )
            {
            case 'playerTurn':
                this.gamedatas.undo_allowed = args.args.undo_allowed;
                break;
            case 'endTurn':
            case 'tileChoice':
                this.showFloor(this.currentFloor());
                break;
            case 'chooseCharacter':
                // If hand is already loaded, don't reload because it adds extra cards on [random walls x random w/ advanced]
                var player_id = args.args.player_id;
                var hand_stock = player_id == this.player_id || player_id == 0 ? this.myHand : this.playerHands[player_id];
                if (hand_stock.count() == 0)
                    this.loadPlayerHand(hand_stock, args.args.cards, [], false);
                // if (this.myHand.count() == 0)
                //     this.loadPlayerHand(this.myHand, args.args.cards, [], false);
                break;
            case 'cardChoice':
                if (args.args.spotter_card && (this.isCardChoice('spotter1') || this.isCardChoice('spotter2'))) {
                    var card = args.args.spotter_card;
                    if (card.type == 3) {
                        dojo.place( this.eventCardHtml(card), 'spotter_card' );
                    } else {
                        var cardType = parseInt(card.type, 10);
                        var cardIndex = parseInt(card.type_arg, 10) - 1;
                        var id = ((cardType - 4) * 16) + cardIndex;
                        dojo.place( this.patrolCardHtml(card, id, false), 'spotter_card' );
                    }
                    this.addCardTooltip(card, 'event_card_dialog');
                    this.displayElement('temp_display');
                    dojo.removeClass('spotter_card_wrapper', 'hidden');
                }
                // Crystal Ball, player can choose to reorder the 3 upcoming events
                if (args.args.event_cards) {
                    this.setupCrystalBallCards(args.args.event_cards);
                }
                // Stethoscope, player can reroll one die
                if (this.isCurrentPlayerActive() && args.args.rolls) {
                    var rolls = [];
                    for (i in args.args.rolls) {
                        rolls.push(args.args.rolls[i].type_arg);
                    }
                    if ($("rolled_dice_stethoscope") === null) {
                        this.createDice('safe', 'safe', rolls, true);
                    }
                    this.setupStethoscope(rolls);
                }
                break;
            case 'proposeTrade':
                if (this.isCurrentPlayerActive()) {
                    this.proposeTrade(args.args);
                }
                break;
            case 'confirmTrade':
                if (this.isCurrentPlayerActive()) {
                    this.confirmTrade(args.args);
                }
                break;
            case 'confirmRookMove':
                var tile_id = 'tile_' + args.args.destination_id;
                if ($(tile_id)) {
                    dojo.addClass(tile_id, 'highlight');
                }
                this.showFloor(args.args.floor);
                break;
            case 'drawToolsAndDiscard':
                if (this.isCurrentPlayerActive()) {
                    this.drawToolsAndDiscard(args.args.tools);
                }
                break;
            case 'takeCards':
                if (this.isCurrentPlayerActive()) {
                    this.takeCards(args.args);
                }
                break;
           
            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            case 'cardChoice':
                this.hideElement('temp_display');
                dojo.addClass('crystal_ball_wrapper', 'hidden');
                $('crystal_ball_cards').innerHTML = '';
                dojo.addClass('spotter_card_wrapper', 'hidden');
                $('spotter_card').innerHTML = '';
                // Clean up rolled and alternative dice from stethoscope
                if ($("dice_choice"))
                    this.fadeOutAndDestroy($("dice_choice"));
                if ($("rolled_dice_1"))
                    this.fadeOutAndDestroy($("rolled_dice_1"));
                this.disconnectAll();
                break;
            case 'playerTurn':
                break;
            case 'confirmRookMove':
                dojo.query('.tile.highlight').removeClass('highlight');
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                    case 'randomizeWalls':
                        this.addActionButton( 'randomize_all', _('Randomize walls on all the floors'), dojo.hitch(this, 'randomizeWalls', 'all'), null, null, 'gray' );
                        for (var i = 1; i <= this.gamedatas.floor_count; i++) {
                            this.addActionButton( 'randomize_' + i, _('Randomize walls on floor ' + i), dojo.hitch(this, 'randomizeWalls', i), null, null, 'gray' );
                        }
                        this.addActionButton( 'confirm_walls', _('Start the game'), dojo.hitch(this, 'randomizeWalls', 'start') );
                        break;
                    case 'playerTurn':
                        if (this.canEscape()) {
                            this.addActionButton( 'button_escape', _('Escape'), dojo.hitch(this, 'handleEscape') );
                        }
                        if (this.canMove()) {
                            this.addActionButton( 'button_move', _('Move'), dojo.hitch(this, 'handleMoveClick') );
                        }
                        if (this.canPeek()) {
                            this.addActionButton( 'button_peek', _('Peek'), dojo.hitch(this, 'handlePeekClick') );
                        }
                        if (this.canAddSafeDie()) {
                            this.addActionButton( 'button_add_safe_die', _('Add Safe Die'), 'handleAddSafeDie' );
                        }
                        if (this.canRollSafeDice()) {
                            this.addActionButton( 'button_roll_safe_dice', _('Roll Safe Dice'), 'handleRollSafeDice' );
                        }
                        if (this.canHack()) {
                            this.addActionButton( 'button_hack' , _('Hack'), 'handleHack' );
                        }
                        if (this.canTrade()) {
                            this.addActionButton( 'button_trade' , _('Trade'), 'handleTrade' );
                        }
                        if (this.canTakeCards()) {
                            this.addActionButton( 'button_take_cards' , _('Take Cards'), 'handleTakeCards' );
                        }
                        if (this.canPickUpKitty()) {
                            this.addActionButton( 'button_pickup' , _('Pick Up Cat'), 'handlePickUpCat' );
                        }
                        this.addCharacterAction();
                        this.addActionButton( 'button_pass', _('End turn'), 'handlePassClick' );
                        break;
                    case 'cardChoice':
                        if (this.isCardChoice('thermal-bomb')) {
                            var floor = this.currentFloor();
                            if (floor <= this.gamedatas.floor_count) {
                                this.addActionButton('button_up', _('Up'), dojo.hitch(this, 'handleCardChoiceButton', floor + 1));
                            }
                            if (floor > 1) {
                                this.addActionButton('button_down', _('Down'), dojo.hitch(this, 'handleCardChoiceButton', floor - 1));
                            }
                        } else if(this.isCardChoice('peterman2')) {
                            var floor = this.currentFloor();
                            // XY, X = 0 is add, X = 1 is roll, Y is floor
                            var detail = this.gamedatas.gamestate.args.peterman2_detail;
                            if (floor < this.gamedatas.floor_count && detail[floor + 1]) {
                                this.addActionButton('button_add_die_up', _('Add Safe Die (Up)'), dojo.hitch(this, 'handleCardChoiceButton', floor + 1));
                                this.addActionButton('button_roll_dice_up', _('Roll Safe Dice (Up)'), dojo.hitch(this, 'handleCardChoiceButton', floor + 11));
                            }
                            if (floor > 1 && detail[floor - 1]) {
                                this.addActionButton('button_add_die_down', _('Add Safe Die (Down)'), dojo.hitch(this, 'handleCardChoiceButton', floor - 1));
                                this.addActionButton('button_roll_dice_down', _('Roll Safe Dice (Down)'), dojo.hitch(this, 'handleCardChoiceButton', floor + 9));
                            }
                        } else if(this.isCardChoice('acrobat2') && this.actionsRemaining() >= 3) {
                            var floor = this.currentFloor();
                            if (floor < this.gamedatas.floor_count) {
                                this.addActionButton('button_acrobat_up', _('Move Up'), dojo.hitch(this, 'handleCardChoiceButton', floor + 1));
                            }
                            if (floor > 1) {
                                this.addActionButton('button_acrobat_down', _('Move Down'), dojo.hitch(this, 'handleCardChoiceButton', floor - 1));
                            }
                        } else if(this.isCardChoice('crystal-ball')) {
                            this.addActionButton('crystal_ball_button', _('Confirm event order'), 'handleMultipleIdCardChoiceButton');
                        } else if(this.isCardChoice('spotter1') || this.isCardChoice('spotter2')) {
                            this.addActionButton('top', _('Keep on top'), dojo.hitch(this, 'handleCardChoiceButton', 1));
                            this.addActionButton('bottom', _('Put on bottom'), dojo.hitch(this, 'handleCardChoiceButton', 2), null, null, 'gray');
                        }
                        if (this.canCancelCardChoice()) {
                            this.addActionButton('button_cancel', _('Cancel'), 'handleCancelCardChoice');
                        }
                        break;
                    case 'tileChoice':
                        this.addActionButton('button_trigger', _('Trigger Alarm'), dojo.hitch(this, 'handleTileChoiceButton', 0));
                        if (this.canHackAlarm()) {
                            this.addActionButton('button_hack_alarm', _('Hack Alarm'), dojo.hitch(this, 'handleTileChoiceButton', 1));
                        }
                        if (this.canUseExtraAction()) {
                            this.addActionButton('button_extra_action', _('Use an Extra Action'), dojo.hitch(this, 'handleTileChoiceButton', 2));
                        }
                        break;
                    case 'playerChoice':
                        // Add players to the action bar so active player can choose
                        var players = this.gamedatas.players;
                        var player_tokens = this.gamedatas.player_tokens;
                        for (i in player_tokens) {
                            var token = player_tokens[i];
                            var player_id = token.type_arg;
                            var player = players[player_id];
                            var character_type = player.character.type_arg - 1;
                            this.addActionButton('button_player_' + player_id, ' ' + player.player_name + '\r\n(' + _(this.gamedatas.card_info[0][character_type]['title']) + ')', dojo.hitch(this, 'handlePlayerChoice', token['id']) );
                            $('button_player_' + player_id).innerHTML = '<span style="white-space: pre;line-height: 25px;margin-left: 5px;">' + $('button_player_' + player_id).innerHTML + '</span>';
                            dojo.style('button_player_' + player_id, 'display', 'inline-flex');
                            var bg_col = character_type % 2,
                                bg_row = Math.floor(character_type / 2);
                            dojo.place(this.format_block('jstpl_meeple', {
                                meeple_id : 'action_bar_' + token['id'],
                                meeple_background : g_gamethemeurl + '/img/meeples.png',
                                meeple_bg_pos : -(bg_col * 35) + 'px ' + -(bg_row * 50) + 'px',
                                player_color: this.gamedatas.players[player_id].player_color
                            }), 'button_player_' + player_id, 'first');
                        }
                        if (args.context !== "squeak")  // cannot cancel Squeak event
                            this.addActionButton('button_cancel', _('Cancel'), 'handleCancelPlayerChoice');
                        break;
                    case 'proposeTrade':
                    case 'confirmTrade':
                        this.addActionButton('button_cancel', _('Cancel Trade'), 'handleCancelTrade');
                        break;
                    case 'confirmRookMove':
                        this.addActionButton('button_confirm', _('Confirm move'), 'handleConfirmRookMove');
                        this.addActionButton('button_cancel', _('Cancel move'), 'handleCancelRookMove');
                        break;
                    case 'specialChoice':
                        if (args.show_cancel)
                            this.addActionButton('button_cancel', _('Cancel'), 'handleCancelSpecialChoice');
                        break;
                }
                if (args && 'undo_allowed' in args) {
                    this.gamedatas.undo_allowed = args.undo_allowed;
                }
                if (this.gamedatas.undo_allowed > 0) {
                    if (this.gamedatas.undo_allowed == 1) {
                        var btn_label = _('Restart turn');
                        var btn_tooltip = _("You can restart your turn if you did not reveal any hidden information nor did not triggered any random event (die roll...)");
                    } else {
                        var btn_label = _('Undo last actions');
                        var btn_tooltip = _("You can undo the last actions you made up to the last action that revealed any hidden information nor triggered any random event (die roll...)");
                    }
                    this.addActionButton('button_undo', btn_label, 'handleRestartTurnClick', null, false, 'red');
                    this.addTooltip('button_undo', btn_tooltip, '');
                }
            }
        },
        
        // setupPatrolItem: function(floor, card_div, card_type_id, card_id) {
        //     var key = floor + this.gamedatas.floor_count;
        //     var size_sq = this.gamedatas.square_size * this.gamedatas.square_size;
        //     card_div.innerText = this.gamedatas.patrol_types[key].cards[card_type_id % size_sq].name;
        // },

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */
        changeMainBar: function(message) {
            this.removeActionButtons();
            $("pagemaintitletext").innerHTML = message;
        },

        getHandStock: function() {
            if (this.gamedatas.solo_characters > 1) {
                var active_player_id = this.gamedatas.active_player_id;
                return active_player_id == this.player_id ? this.myHand : this.playerHands[active_player_id];
            } else {
                return this.myHand;
            }
        },

        // Get card unique identifier based on its row and col
        getCardUniqueId : function(type, index) {
            return parseInt(type, 10) * 100 + parseInt(index, 10);
        },

        getTileUniqueId : function(row, col) {
            return parseInt(row, 10) * 4 + parseInt(col, 10);
        },

        createTileContainer: function(floor, tile) {
            var div_id = 'tile_' + tile.id + '_container';
                
            var idx = parseInt(tile.location_arg, 10);
            var size = this.gamedatas.square_size;
            var row = Math.floor(idx / size);
            var col = idx % size;
            dojo.place(this.format_block('jstpl_tile_container', {
                id : tile.id, 
                x : (this.cardwidth + 36) * col,
                y : (this.cardheight + 36) * row,
                name : tile.type + tile.safe_die
            }), 'floor' + floor);
            
            dojo.connect( $(div_id), 'onclick', this, function(evt) {
                this.handleTileClick(evt, tile.id);
            });

            var zone = new ebg.zone();
            var zoneId = 'tile_' + tile.id + '_tokens';
            zone.create( this, zoneId, 24, 24 );
            zone.setPattern( 'grid' );
            this.zones[zoneId] = zone;

            zone = new ebg.zone();
            zoneId = 'tile_' + tile.id + '_meeples';
            zone.create( this, zoneId, 35, 50 );
            zone.setPattern( 'grid' );
            this.zones[zoneId] = zone;
        },

        createCardZone: function(stock, card_div, card_type_id, card_div_id) {
            var card = stock.getFirstItemOfType(card_type_id);
            var card_type = Math.floor(card_type_id / 100);
            if (card_type === 0) { // Character
                dojo.place('<div id="card_' + card.id + '_tokens" class="card-zone"></div>', card_div_id);
                var zone = new ebg.zone();
                var zoneId = 'card_' + card.id + '_tokens';
                zone.create( this, zoneId, 24, 24 );
                zone.setPattern( 'grid' );
                this.zones[zoneId] = zone;
            } else if (card_type_id == 210) {
                dojo.place('<div id="card_kitty_warning"></div>', card_div_id);
                if (this.gamedatas.gamestate.args.kitty_escaped) {
                    this.catEscaped();
                }
            }
        },

        playTileOnTable: function(floor, tile) {
            // console.log('playTileOnTable', tile.type, tile.type != 'shaft');
            var div_id = 'tile_' + tile.id,
                preview_div_id = 'tile_' + tile.id + '_preview';
            if ($(div_id)) {
                dojo.destroy(div_id);
            }
            if ($(preview_div_id)) {
                dojo.destroy(preview_div_id);
            }
                
            if (tile.type != 'shaft') {
                var bg_row = Math.floor(tile.type_arg / 2) * -100;
                var bg_col = (tile.type_arg % 2) * -100;
                dojo.place(this.format_block('jstpl_tile', {
                    id : tile.id, 
                    bg_image: g_gamethemeurl + 'img/tiles.jpg',
                    bg_position: bg_col.toString() + '% ' + bg_row.toString() + '%',
                    name : tile.type + tile.safe_die
                }), div_id + '_container');
            } else {
                dojo.place(this.format_block('jstpl_tile_shaft', {
                    id : tile.id, 
                    name : tile.type + tile.safe_die
                }), div_id + '_container');
            }

            var square_size = this.gamedatas.square_size;
            var preview_row = Math.floor(tile.location_arg / square_size) * 28 + 8;
            var preview_col = (tile.location_arg % square_size) * 28 + 8;
            dojo.place(this.format_block('jstpl_tile_preview', {
                id : tile.id,
                tile_type : tile.type,
                preview_row: preview_row,
                preview_col: preview_col
            }), 'floor' + floor.toString() + '_preview', 'first');

            if (tile.type != 'back' && tile.type != 'shaft') {               
                var tooltipHtml = this.format_block('jstpl_tile_tooltip', {
                    id : tile.id, 
                    bg_image: g_gamethemeurl + 'img/tiles.jpg',
                    bg_position: bg_col.toString() + '% ' + bg_row.toString() + '%'
                });
                this.addTooltipHtml(div_id + '_container', tooltipHtml);
            }
        },

        playWallOnTable: function(wall) {
            var div_id = 'wall_' + wall.id;
                
            var idx = parseInt(wall.position, 10);
            var square_size = this.gamedatas.square_size;
            var dec = square_size - 1;
            var row = Math.floor(idx / dec);
            var col = idx % dec;
            var sizePlusPadding = 120 + 36;
            var x = wall.vertical == '1' ? 142.5 + (col * sizePlusPadding) : 10 + (row * sizePlusPadding);
            var y = wall.vertical == '1' ? 20 + (row * sizePlusPadding) : 152.5 + (col * sizePlusPadding);
            if ($('floor' + wall.floor)) {
                dojo.place(this.format_block('jstpl_wall', {
                    wall_id : wall.id,
                    wall_direction : wall.vertical == '1' ? 'vertical' : 'horizontal', 
                    x : x,
                    y : y
                }), 'floor' + wall.floor);

                dojo.connect( $(div_id), 'onclick', this, function(evt) {
                    dojo.stopEvent(evt);
                    if (this.checkAction('selectCardChoice')) {
                        this.ajaxcall('/burglebros/burglebros/selectCardChoice.html', { lock: true, selected_type: 'wall', selected_id: wall.id }, this, function () {
                            // dojo.destroy(div_id);
                        }, console.error);
                    }
                });
            }
        },

        createPlayerToken: function(id, player_id) {
            // console.log("createPlayerToken", id,  player_id);
            var character = this.gamedatas.players[player_id].character,
                index = character.type_arg - 1,
                bg_col = index % 2,
                bg_row = Math.floor(index / 2);
            dojo.place(this.format_block('jstpl_meeple', {
                meeple_id : id,
                meeple_background : g_gamethemeurl + '/img/meeples.png',
                meeple_bg_pos : -(bg_col * 35) + 'px ' + -(bg_row * 50) + 'px',
                player_color: this.gamedatas.players[player_id].player_color
            }), 'token_container');
        },

        moveToken: function(token_type, token) {
            // console.log('moveToken', token_type, token);
            // Show floor before moving token to avoid vertical display
            if (token.location == 'tile') {
                var node = $('tile_' + token.location_arg + '_container');
                if (node) {
                    var floor = node.parentNode.id.slice(-1);
                    this.showFloor(floor);
                }
            }
            if (token_type === 'player') {
                var meepleZoneId = 'tile_' + token.location_arg + '_meeples';
                // Guarantee meeple token is created
                if ($('meeple_' + token.id)) {
                    this.zones[meepleZoneId].placeInZone('meeple_' + token.id);
                } else {
                    this.createPlayerToken(token.id, token.type_arg);
                    if (this.canMoveToken(token)) {
                        this.moveToken('player', token);
                    } 
                }
            } else {
                var zoneId = token.location + '_' + token.location_arg + '_tokens';
                if (this.zones[zoneId]) {
                    this.zones[zoneId].placeInZone(token_type + '_token_' + token.id);
                }
            }
        },

        removeToken: function(token_type, id) {
            var deck = this.gamedatas[token_type + '_tokens'];
            // console.log('*** deck', deck);
            var token = deck[id];
            // console.log('removeToken', token);
            // TODO Token are sometimes shown vertically, need to find why
            if (token && this.canMoveToken(token)) {
                if (token_type === 'player') {
                    var meepleZoneId = 'tile_' + token.location_arg + '_meeples';
                    console.log("remove from zone", meepleZoneId);
                    this.zones[meepleZoneId].removeFromZone('meeple_' + token.id, token.location === 'roof');
                } else {
                    var zoneId = token.location + '_' + token.location_arg + '_tokens';
                    this.zones[zoneId].removeFromZone(token_type + '_token_' + id, token_type === 'generic' || token.location === 'deck');
                }
            } else if (token && token.location === 'roof') {
                this.fadeOutAndDestroy('meeple_' + token.id);
            }
        },

        catEscaped: function() {
            $("card_kitty_warning").innerHTML = _("Escaped");
        },

        createGuardToken: function(token_id) {
            dojo.place(this.format_block('jstpl_guard_token', {
                token_id : token_id,
            }), 'token_container');
        },

        createPatrolToken: function(token, die_num) {
            var div_id = 'patrol_token_' + token.id;
            if ($(div_id)) {
                $(div_id).innerText = die_num;
            } else {
                dojo.place(this.format_block('jstpl_patrol_die', {
                    token_id : token.id,
                    num_spaces : die_num,
                }), 'token_container');
            }

            if (this.canMoveToken(token)) {
                this.moveToken(token.type, token);
            }
        },

        createSafeToken: function(token, die_num) {
            var div_id = 'crack_token_' + token.id;
            if ($(div_id)) {
                $(div_id).innerText = 'x' + die_num;
            } else {
                dojo.place(this.format_block('jstpl_safe_die', {
                    token_id : token.id,
                    die_num : die_num,
                }), 'token_container');
            }

            if (token.location !== 'deck') {
                this.moveToken('crack', token);
            } 
        },

        createGenericToken: function(token, wrapper='token_container') {
            var tokenType = this.gamedatas.token_types[token.type];
            dojo.place(this.format_block('jstpl_generic_token', {
                token_id : token.id,
                token_color : tokenType.color,
                token_type : token.type,
                token_background : g_gamethemeurl + '/img/tokens.jpg',
                token_bg_pos : (tokenType.id * -32) - 4,
                token_letter : token.letter
            }), wrapper);
        },

        destroyGenericToken: function(id) {
            dojo.destroy('generic_token_' + id);
        },

        createCardToken: function(id, type, count, extra_classes) {
            var card_type = this.gamedatas.card_types[type];
            dojo.place(this.format_block('jstpl_card_token', {
                tile_id : id,
                card_type : card_type.name,
                card_count : count || 1,
                token_background : g_gamethemeurl + '/img/tokens.jpg',
                extra_classes: extra_classes
            }), 'tile_' + id + '_cards');
        },

        destroyCardToken: function(id) {
            dojo.destroy('card_token_' + id);
        },

        createPlayerBoard: function(id) {
            var tokenType = this.gamedatas.token_types['stealth'];
            dojo.place(this.format_block('jstpl_player_zone', {
                id : id,
            }), 'player_board_' + id);

            var zone = new ebg.zone();
            var zoneId = 'player_' + id + '_tokens';
            zone.create( this, zoneId, 24, 24 );
            zone.setPattern( 'grid' );
            this.zones[zoneId] = zone;
            dojo.place(this.format_block('jstpl_player_escaped', {
                id : id,
            }), 'player_board_' + id);
        },

        activatePlayer: function(active_player_id) {
            // Solo multi-characters games >> Move active player's hand to the top and the other hands consecutively
            var player_ids = Object.keys(this.gamedatas.players);
            var index = player_ids.indexOf('' + active_player_id); // use of toString() wether active_player_id is int or str
            if (index == -1) {
                console.error("Coulnd't find this active player: ", active_player_id);
                return;
            }
            this.gamedatas.active_player_id = active_player_id;
            for (let wrap_index = 1; wrap_index <= player_ids.length; wrap_index++) {
                let player_id = player_ids[index];
                let node = $('player_hand_content_' + player_id);
                let destination = $('player_hand_wrap_' + wrap_index);
                let anim = this.slideToObject(node, destination, 1000);
                let handStock = player_id == this.player_id ? this.myHand : this.playerHands[player_id];
                anim.play();
                this.attachToHTML(node, destination);
                index = ++index >= player_ids.length ? 0 : index;
            }
        },
        attachToHTML: function(child, parent) {
            // Attach element to HTML parent (e.g. after slideTo) to force child to move with parent
            // console.log("attachToHTML", child, parent);
            $(parent).appendChild( $(child) );
            // $(child).removeAttribute("style");
            dojo.style(child, 'position', 'inherit');
        },

        createGuardPath: function(floor, guard_path) {
            // console.log('guard_path', guard_path);
            if (guard_path === null)
                return false;
            // Create a SVG path preview on the floor preview zone
            var HTML_path = '';
            // Create path
            for (var i = guard_path.length - 1; i >= 1; i--) {
                var previous_pos = guard_path[i-1];
                var x1 = this.path_x_offset + this.path_tile_offset * this.calcSvgPosX(previous_pos);
                var y1 = this.path_y_offset + this.path_tile_offset * this.calcSvgPosY(previous_pos);
                var current_pos = guard_path[i];
                var x2 = this.path_x_offset + this.path_tile_offset * this.calcSvgPosX(current_pos);
                var y2 = this.path_y_offset + this.path_tile_offset * this.calcSvgPosY(current_pos);
                HTML_path += this.format_block('jstpl_path_line', {
                    x1: x1,
                    y1: y1,
                    x2: x2,
                    y2: y2,
                    floor: floor,
                    position: current_pos
                });
            }
            // Append guard position (circle)
            HTML_path += this.createGuardPreviewHTML(floor, guard_path);
            // Wrap with svg tag
            HTML_path = '<svg id="floor' + floor + '_svg_path">' + HTML_path + '</svg>';
            dojo.destroy($('floor' + floor + '_svg_path'));
            dojo.place( HTML_path, 'floor' + floor + '_path_preview');
        },

        updateGuardPath: function(floor, guard_path, position) {
            // Delete current path
            if (($('path_preview_floor'+floor+'_position'+position)))
                this.fadeOutAndDestroy('path_preview_floor'+floor+'_position'+position);
            // Update guard position
            var cx = this.path_x_offset + this.path_tile_offset * this.calcSvgPosX(guard_path[0]);
            var cy = this.path_y_offset + this.path_tile_offset * this.calcSvgPosY(guard_path[0]);
            $('guard_preview_floor' + floor).setAttribute("cx", cx);
            $('guard_preview_floor' + floor).setAttribute("cy", cy);
        },
        createGuardPreviewHTML: function(floor, guard_path) {
            var x = this.calcSvgPosX(guard_path[0]);
            var y = this.calcSvgPosY(guard_path[0]);
            return this.format_block('jstpl_path_circle', {
                cx: this.path_x_offset + this.path_tile_offset * x,
                cy: this.path_y_offset + this.path_tile_offset * y,
                floor: floor,
            });
        },
        calcSvgPosX: function(position) {
            var size_sq = this.gamedatas.square_size;
            return (position + 1) % size_sq == 0 ? size_sq : position % size_sq;
        },
        calcSvgPosY: function(position) {
            return Math.floor(position / this.gamedatas.square_size);
        },

        addCharacterAction: function() {
            var character = this.gamedatas.gamestate.args.character.name;

            if (!this.gamedatas.gamestate.args.character_action_enabled) {
                return;
            }

            var typeToTitle = {
                acrobat1: 'Acrobat: Flexibility',
                acrobat2: 'Acrobat: Climb Window',
                hacker2: 'Hacker: Laptop',
                hawk1: 'Hawk: X-Ray',
                hawk2: 'Hawk: Enhance',
                juicer1: 'Juicer: Crybaby',
                juicer2: 'Juicer: Reroute',
                peterman2: 'Peterman: Drill',
                raven1: 'Raven: Distract',
                raven2: 'Raven: Disrupt',
                rigger2: 'Rigger: Tinker',
                rook1: 'Rook: Orders',
                rook2: 'Rook: Disguise',
                spotter1: 'Spotter: Clairvoyance',
                spotter2: 'Spotter: Precognition'
            };

            if (typeToTitle[character]) {
                this.addActionButton('button_character', _(typeToTitle[character]), 'handleCharacterAction');
            }
        },

        canUseCharacterAction: function() {
        },

        canEscape: function() {
            return this.gamedatas.gamestate.args.escape && this.actionsRemaining() >= 1;
        },
        canMove: function() {
            return this.actionsRemaining() >= 1;
        },
        canPeek: function() {
            return this.gamedatas.gamestate.args.peekable.length > 0 && this.actionsRemaining() >= 1;
        },

        canAddSafeDie: function() {
            return this.gamedatas.gamestate.args.tile.type === 'safe' &&
                this.actionsRemaining() >= 2 &&
                !this.tileContainsToken('open');
        },

        canRollSafeDice: function() {
            return this.gamedatas.gamestate.args.tile.type === 'safe' &&
                this.actionsRemaining() >= 1 &&
                !this.tileContainsToken('open') && 
                (this.tileContainsToken('crack') || this.gamedatas.gamestate.args.character.name == 'peterman1');
        },

        canHack: function() {
            return this.gamedatas.gamestate.args.tile.type.endsWith('-computer') && this.actionsRemaining() >= 1;
        },

        canHackAlarm: function() {
            return this.gamedatas.gamestate.args.can_hack;
        },

        canCancelCardChoice: function() {
            var type = this.gamedatas.gamestate.args.card['type'];
            var type_arg = this.gamedatas.gamestate.args.card['type_arg'];
            if (type_arg == 3 || type_arg == 17 || type_arg == 18) // crystal-ball or spotter1 or spotter2
                return false;   // cannot cancel when player has seen some top cards of the deck
            return type == 1 || type == 0; // Tools and Characters
        },

        canUseExtraAction: function() {
            return this.gamedatas.gamestate.args.can_use_extra_action;
        },

        canMoveToken: function(token) {
            return ['tile', 'card', 'player'].indexOf(token.location) !== -1;
        },

        canTrade: function() {
            // return this.gamedatas.gamestate.args.other_players > 0;
            return this.gamedatas.gamestate.args.tradable;
        },

        canTakeCards: function() {
            return this.gamedatas.gamestate.args.tile.type === 'safe' &&
                Object.keys(this.gamedatas.gamestate.args.tile_cards).length > 0;
        },

        canPickUpKitty: function() {
            var type_id = this.getCardTypeForName(2, 'persian-kitty');
            // return this.tileContainsToken('cat') && this.handContainsCard(type_id);
            return this.tileContainsToken('cat');
        },

        isCardChoice: function(name) {
            return this.gamedatas.gamestate.args.card_name === name;
        },

        playerChoiceContext: function() {
            return this.gamedatas.gamestate.args.context;
        },

        currentFloor: function() {
            if (this.gamedatas.gamestate.args) {
                return parseInt(this.gamedatas.gamestate.args.floor, 10);
            } else {
                return 1;
            }
        },

        actionsRemaining: function() {
            return parseInt(this.gamedatas.gamestate.args.actions_remaining, 10);
        },

        tileContainsToken: function(name) {
            var tokens = this.gamedatas.gamestate.args.tile_tokens;
            for(var tokenId in tokens) {
                if (tokens[tokenId].type == name) {
                    return true;
                }
            }
            return false;
        },

        getCardTypeForName: function(type_id, name) {
            var deck_types = this.gamedatas.card_types[type_id].cards;
            for (var idx = 0; idx < deck_types.length; idx++) {
                if (deck_types[idx].name === name) {
                    return deck_types[idx].index;
                }
            }
            return;
        },

        handContainsCard: function(type_id) {
            var active_player_id = this.gamedatas.solo_characters > 1 ? this.gamedatas.active_player_id : this.player_id;
            var hand = this.gamedatas.players[active_player_id].hand;
            for (var id in hand) {
                var card = hand[id];
                if (card.type_arg == type_id) {
                    return true;
                }
            }
            return false;
        },

        showFloor: function(floorNum) {
            this.selected_floor = floorNum;
            for (var floor = 1; floor <= this.gamedatas.floor_count; floor++) {
                var floorId = 'floor' + floor.toString() + '_tiles';
                var patrolId = 'patrol_wrapper' + floor.toString();
                var previewId = 'floor' + floor.toString() + '_preview';
                if (floor == floorNum) {
                    dojo.removeClass(floorId, 'hidden');
                    dojo.removeClass(patrolId, 'hidden');
                    dojo.addClass(previewId, 'selected');
                } else {
                    dojo.addClass(floorId, 'hidden');
                    dojo.addClass(patrolId, 'hidden');
                    dojo.removeClass(previewId, 'selected');
                }
            }
        },

        showPlayer: function(playerId) {
            // Find player token, show floor and bounce the player token
            var token = Object.values(this.gamedatas.player_tokens).filter(token => token.type_arg == playerId)[0];
            var meeple_node = $('meeple_' + token.id);
            if (token.location != 'tile' || !meeple_node) {
                this.showMessage( _("This player's meeple is not on the board"), "error" );
                return;
            }
            // Find and show player floor
            var floor = meeple_node.closest('div.floor').id.slice(-1);
            this.showFloor(floor);
            // Bounce effect
            dojo.addClass( meeple_node, 'bounce' );
            setTimeout( function() {
                var nodes = dojo.query(".meeple.bounce");
                dojo.forEach( nodes, function (node, i) {
                    dojo.removeClass(node, "bounce");
                })
            }, 2000);  
        },

        showDistribution: function() {
            // Create the new dialog over the play zone. You should store the handler in a member variable to access it later
            this.distributionDialog = new ebg.popindialog();
            this.distributionDialog.create( 'tile_distribution' );
            this.distributionDialog.setTitle( _("Room distribution") );

            var display_tiles = [];
            var flipped_tiles = this.gamedatas.flipped_tiles;
            for (id in flipped_tiles) {
                var tile = flipped_tiles[id];
                (display_tiles[tile.type] = display_tiles[tile.type] || []).push({
                    'type' : tile.type,
                    'floor' : parseInt(tile.location.slice(-1), 10),
                    'location' : this.gamedatas.patrol_names[tile.location_arg]['name'],
                });
            }

            // Create the HTML of my dialog. 
            var html = this.format_block( 'jstpl_distribution_dialog_header', {
                room_type: _('Room type'),
                discovered: _('Discovered'),
                floor_1: _('Floor 1'),
                floor_2: _('Floor 2'),
                floor_3: _('Floor 3'),
            } );

            var tiles = this.gamedatas.tile_distribution;
            for (type in tiles) {
                var tile = tiles[type];
                var name = tile['name'];
                // Concatenate all the locations of display_tiles for each floor
                var floors = {};
                for (var i = 1; i <= 3; i++) {
                    if (display_tiles[type]) {
                        floors[i] = display_tiles[type].filter(tile => tile.floor == i).map(tile => tile.location).join(" ");  
                    } else {
                        floors[i] = "";
                    }
                }
                var discovered = display_tiles[type] ? display_tiles[type].length : 0;
                html += this.format_block( 'jstpl_distribution_dialog_row', { 
                    room_type: name,
                    discovered: discovered + '/' + tile['nb'],
                    floor_1: floors[1],
                    floor_2: floors[2],
                    floor_3: floors[3],
                    type_class: type,
                } );
            }

            html += this.format_block( 'jstpl_distribution_dialog_footer', { 
                close_button: _('Close')
            } );

            // Show the dialog
            this.distributionDialog.setContent( html ); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            // Hide last column if less than 3 floors
            if (this.gamedatas.floor_count < 3) {
                dojo.query('#distribution_dialog .last_column').addClass('hidden');
            }
            // Append current token count to each computer row
            for (var token_id in this.gamedatas.generic_tokens) {
                var token = this.gamedatas.generic_tokens[token_id];
                if (token.type == 'hack' && token.location == 'tile') {
                    var computer_type = flipped_tiles[token.location_arg]['type'];
                    var wrapper = dojo.query("#distribution_dialog td." + computer_type)[0];
                    this.createGenericToken(token, wrapper);
                }
            }
            this.distributionDialog.show();

            // Now that the dialog has been displayed, you can connect your method to some dialog elements
            // Example, if you have an "OK" button in the HTML of your dialog:
            dojo.connect( $('close_button'), 'onclick', this, function(evt){
                evt.preventDefault();
                this.distributionDialog.destroy();
            } );
        },

        playerEscaped: function(player_id)  {
            // Change display of player board when escaped
            dojo.addClass("overall_player_board_" + player_id, 'escaped');
            dojo.removeClass("player_" + player_id + "_escaped", 'hidden');
            dojo.addClass("player_" + player_id + "_escaped", 'escaped');
            // Place token and escape text
            var token = { 'type':'open', 'letter':'O' };
            var tokenType = this.gamedatas.token_types[token.type];
            $('player_' + player_id + '_escaped').innerHTML = '   ' + _('Player escaped');
            dojo.place(this.format_block('jstpl_generic_token', {
                token_id : 'player_' + player_id + '_escaped',
                token_color : tokenType.color,
                token_type : token.type,
                token_background : g_gamethemeurl + '/img/tokens.jpg',
                token_bg_pos : (tokenType.id * -32) - 4,
                token_letter : token.letter
            }), 'player_' + player_id + '_escaped', 'first');
            dojo.style('generic_token_player_' + player_id + '_escaped', 'display', 'inline-block');
        },

        loadPatrolDiscard: function(floor, card) {
            // console.log("loadPatrolDiscard", floor, card);
            var patrolKey = 'patrol' + floor;
            // If Square size is 4, use the Patrol Stock cards
            if (this.gamedatas.square_size == 4) {
                var existing = this[patrolKey].getAllItems();
                for(var idx = 0; idx < existing.length; idx++) {
                    var discardDiv = this[patrolKey].getItemDivId(existing[idx].id);
                    this.removeTooltip(discardDiv);
                }
                this[patrolKey].removeAll();
        
                if (card) {
                    var cardType = parseInt(card.type, 10);
                    var cardIndex = parseInt(card.type_arg, 10) - 1;
                    var id = ((cardType - 4) * 16) + cardIndex;
                    this[patrolKey].addToStockWithId(id, card.id);

                    var tooltipHtml = this.patrolCardHtml(card, id, this.gamedatas[patrolKey + '_discard']);
                    var divId = this[patrolKey].getItemDivId(card.id);
                    this.addTooltipHtml(divId, tooltipHtml);
                } else {
                    this[patrolKey].addToStockWithId(51, 51);
                }
            } else {
                if (card) {
                    dojo.removeClass('patrol_wrapper' + floor, 'patrol_card_back');
                    dojo.addClass('patrol_wrapper' + floor, 'whiteblock');
                    dojo.removeClass(patrolKey, 'hidden');
                    var index = card.type_arg - 1;
                    var label = this.gamedatas.patrol_names[index].name; // A1, B2...
                    dojo.query('#' + patrolKey + ' .current_patrol').forEach(function(e) {
                        dojo.removeClass(e, 'current_patrol');
                        e.innerHTML = '';
                    });
                    var tile_id = patrolKey + label;
                    $(tile_id).innerHTML = label;
                    dojo.addClass(tile_id, 'current_patrol');
                }
            }
        },

        loadPlayerHand: function(handStock, hand, discard_ids, tradable) {
            // console.log('loadPlayerHand', hand);
            for(var cardId in hand) {
                var card = hand[cardId];
                if (tradable && (card.type == 0 || card.type == 3)) {
                    continue;
                }

                var cardTypeId = this.getCardUniqueId(card.type, card.type_arg);
                if (!handStock.getItemById(cardId)) {
                    handStock.addToStockWithId(cardTypeId, cardId);
                    this.addCardTooltip(card, handStock.getItemDivId(cardId));
                }
            }
            for(var idx = 0; idx < discard_ids.length; idx++) {
                var discard_id = discard_ids[idx];
                if (handStock.getItemById(discard_id)) {
                    handStock.removeFromStockById(discard_id);
                }
            }
        },

        addCardTooltip: function(card, divId) {
            // console.log("addCardTooltip", card, divId);
            var typeInfo = this.gamedatas.card_types[card.type];
            if (typeInfo === undefined)    // patrol card
                return false;
            var index = card.type == 0 ? card.type_arg - 1 : card.type_arg;
            var bg_row = Math.floor(index / 2) * -100;
            var bg_col = (index % 2) * -100;
            var card_info = this.gamedatas.card_info[card.type][card.type_arg - 1];
            var jstpl = card.type == 0 ? 'jstpl_card_tooltip' : 'jstpl_event_card_tooltip'
            var tooltipHtml = this.format_block(jstpl, {
                id : card.id, 
                bg_image: g_gamethemeurl + 'img/' + typeInfo.name + '.jpg',
                bg_position: bg_col.toString() + '% ' + bg_row.toString() + '%',
                card_subhead: _(card_info.subhead),
                card_title: _(card_info.title),
                card_ability: _(card_info.ability),
                card_tooltip: _(card_info.tooltip)
            });
            this.addTooltipHtml(divId, tooltipHtml, 700);
        },

        addCardTypesToStock: function(stock, types) {
            // Create cards types:
            for (var type = 0; type < types.length; type++) {
                var typeInfo = gamedatas.card_types[types[type]];
                for (var index = 0; index < typeInfo.cards.length; index++) {
                    // Build card type id
                    var card = typeInfo.cards[index];
                    var cardTypeId = this.getCardUniqueId(card.type, card.index);
                    var cardIndex = card.type == 0 ? card.index - 1 : card.index;
                    stock.addItemType(cardTypeId, cardTypeId, g_gamethemeurl + 'img/' + typeInfo.name + '.jpg', cardIndex);
                }
            }
        },

        showTradeDialog: function(opts) {
            console.log("showTradeDialog", opts);
            var combinedOpts = dojo.mixin({
                l_color: 'black',
                r_color: 'black',
                l_name: 'You',
                r_name: 'Other',
                l_cards: [],
                r_cards: [],
                title: _('Trade Cards'),
                cancel_title: _('Cancel Trade'),
                confirm_title: _('Propose Trade'),
                // Required: close_callback, confirm_callback
            }, opts);
            if (!combinedOpts.l_color) combinedOpts.l_color = 'black';
            var dialog = new ebg.popindialog();
            dialog.create( 'proposeTradeDialog' );
            dialog.setTitle( combinedOpts.title );
            
            var html = this.format_block('jstpl_trade_dialog', {
                p1_color: combinedOpts.l_color,
                p2_color: combinedOpts.r_color,
                p1_name: combinedOpts.l_name,
                p2_name: combinedOpts.r_name,
                cancel_title: combinedOpts.cancel_title,
                confirm_title: combinedOpts.confirm_title,
            });
            
            dialog.setContent( html ); // Must be set before calling show() so that the size of the content is defined before positioning the dialog

            var l_stock = new ebg.stock();
            l_stock.create(this, $('trade_p1'), this.cardwidth, this.cardheight);
            l_stock.image_items_per_row = 2;
            l_stock.setSelectionMode(1);
            l_stock.setSelectionAppearance('class');
            this.addCardTypesToStock(l_stock, [1, 2, 3]);
            this.loadPlayerHand(l_stock, combinedOpts.l_cards, [], true);

            var r_stock = new ebg.stock();
            r_stock.create(this, $('trade_p2'), this.cardwidth, this.cardheight);
            r_stock.image_items_per_row = 2;
            r_stock.setSelectionMode(1);
            r_stock.setSelectionAppearance('class');
            this.addCardTypesToStock(r_stock, [1, 2, 3]);
            this.loadPlayerHand(r_stock, combinedOpts.r_cards, [], true);

            var cards = dojo.mixin({}, combinedOpts.l_cards, combinedOpts.r_cards);
            this.connectTradeButtonHandlers(cards, l_stock, r_stock);
            this.connectTradeButtonHandlers(cards, r_stock, l_stock);

            dialog.show();
            
            // Now that the dialog has been displayed, you can connect your method to some dialog elements
            // Example, if you have an "OK" button in the HTML of your dialog:
            var closeCallback = function(evt) {
                evt.preventDefault();
                l_stock.destroy();
                r_stock.destroy();
                dialog.destroy();
                combinedOpts.close_callback();
            };
            dialog.replaceCloseCallback(dojo.hitch(this, closeCallback));
            dojo.connect( $('trade_cancel_button'), 'onclick', this, closeCallback);
            dojo.connect( $('trade_confirm_button'), 'onclick', this, function(evt) {
                evt.preventDefault();
                var idGetter = function (item) { return item.id; };
                var l_cards = dojo.map(l_stock.getAllItems(), idGetter).join(';');
                var r_cards = dojo.map(r_stock.getAllItems(), idGetter).join(';');
                var params = {
                    l_cards: l_cards,
                    r_cards: r_cards,
                    cleanup: function() {
                        l_stock.destroy();
                        r_stock.destroy();
                        dialog.destroy();
                    }
                };
                combinedOpts.confirm_callback(params);
            });
        },

        proposeTrade: function(args) {
            console.log('proposeTrade', args);
            var p1 = this.gamedatas.players[args.trade.current_player];
            var p2 = this.gamedatas.players[args.trade.other_player];
            this.showTradeDialog({
                l_cards: p1.hand,
                r_cards: p2.hand,
                l_name: _('You'),
                r_name: p2.player_name,
                l_color: p1.player_color,
                r_color: p2.player_color,
                close_callback: dojo.hitch(this, function() {
                    this.handleCancelTrade();
                }),
                confirm_callback: dojo.hitch(this, function(confirmArgs) {
                    if (this.checkAction('proposeTrade')) {
                        var params = { lock: true, p1_cards: confirmArgs.l_cards, p2_cards: confirmArgs.r_cards };
                        this.ajaxcall('/burglebros/burglebros/proposeTrade.html', params, this, confirmArgs.cleanup, console.error);
                    }
                })
            });
        },

        connectTradeButtonHandlers: function(cards, from_stock, to_stock) {
            console.log('connectTradeButtonHandlers', cards);
            dojo.connect( from_stock, 'onChangeSelection', this, function (control_name, item_id) {
                var item = from_stock.getItemById(item_id);
                var anim_from = from_stock.getItemDivId(item_id);
                this.removeTooltip(anim_from);
                to_stock.addToStockWithId(item.type, item.id, anim_from);
                from_stock.removeFromStockById(item_id);
                this.addCardTooltip(cards[item_id], to_stock.getItemDivId(item_id));
            });
        },

        confirmTrade: function(args) {
            console.log('confirmTrade', args);
            var dialog = new ebg.popindialog();
            dialog.create( 'confirmTradeDialog' );
            dialog.setTitle( _("Confirm Trade") );
            
            // Swap for confirming player
            var p1 = this.gamedatas.players[args.trade.other_player];
            var p2 = this.gamedatas.players[args.trade.current_player];
            var html = this.format_block('jstpl_trade_confirmation_dialog', {
                p1_color: p1.color,
                p2_color: p2.color,
                p2_name: p2.player_name,
            });
            
            dialog.setContent( html ); // Must be set before calling show() so that the size of the content is defined before positioning the dialog

            var p1_stock = new ebg.stock();
            p1_stock.create(this, $('trade_p1'), this.cardwidth, this.cardheight);
            p1_stock.image_items_per_row = 2;
            p1_stock.setSelectionMode(0);
            this.addCardTypesToStock(p1_stock, [1, 2, 3]);
            this.loadPlayerHand(p1_stock, args.p1_cards, [], true);

            var p2_stock = new ebg.stock();
            p2_stock.create(this, $('trade_p2'), this.cardwidth, this.cardheight);
            p2_stock.image_items_per_row = 2;
            p2_stock.setSelectionMode(0);
            this.addCardTypesToStock(p2_stock, [1, 2, 3]);
            this.loadPlayerHand(p2_stock, args.p2_cards, [], true);

            dialog.show();
            
            // Now that the dialog has been displayed, you can connect your method to some dialog elements
            // Example, if you have an "OK" button in the HTML of your dialog:
            var closeCallback = function(evt) {
                evt.preventDefault();
                p1_stock.destroy();
                p2_stock.destroy();
                dialog.destroy();
                this.handleCancelTrade();
            };
            dialog.replaceCloseCallback(dojo.hitch(this, closeCallback));
            dojo.connect( $('trade_cancel_button'), 'onclick', this, closeCallback);
            dojo.connect( $('trade_confirm_button'), 'onclick', this, function(evt) {
                evt.preventDefault();
                if (this.checkAction('confirmTrade')) {
                    this.ajaxcall('/burglebros/burglebros/confirmTrade.html', { lock: true }, this, function() {
                        p1_stock.destroy();
                        p2_stock.destroy();
                        dialog.destroy();
                    }, console.error);
                }
            });
        },

        takeCards: function(args) {
            console.log('takeCards', args);
            var player = this.gamedatas.players[this.player_id];
            this.showTradeDialog({
                l_cards: args.tile_cards,
                l_name: _('In Tile'),
                r_name: _('You'),
                r_color: player.color,
                title: _('Take Cards'),
                cancel_title: _('Cancel'),
                confirm_title: _('Take Cards'),
                close_callback: dojo.hitch(this, function() {
                    this.handleCancelTakeCards();
                }),
                confirm_callback: dojo.hitch(this, function(confirmArgs) {
                    if (this.checkAction('confirmTakeCards')) {
                        var params = { lock: true, l_cards: confirmArgs.l_cards, r_cards: confirmArgs.r_cards };
                        this.ajaxcall('/burglebros/burglebros/confirmTakeCards.html', params, this, confirmArgs.cleanup, console.error);
                    }
                })
            });
        },

        eventCardHtml: function(card, card_id='', extra_classes='') {
            var bg_row = Math.floor(card.type_arg / 2) * -100;
            var bg_col = (card.type_arg % 2) * -100;
            return this.format_block('jstpl_event_card', {
                bg_image: g_gamethemeurl + 'img/events.jpg',
                bg_position: bg_col.toString() + '% ' + bg_row.toString() + '%',
                card_id: card_id,
                extra_classes: extra_classes,
            });

        },

        patrolCardHtml: function(card, bg_id, discards) {
            var bg_row = Math.floor(bg_id / 4) * -100;
            var bg_col = (bg_id % 4) * -100;
            
            var discardHtml = '';
            if (discards) {
                discardHtml = '<div class="patrol-discard-container">';
                for(var discardId in discards) {
                    if (discardId != card.id) {   
                        var discard = discards[discardId];
                        var discardIndex = parseInt(discard.type_arg, 10) - 1;
                        var discard_top = Math.floor(discardIndex / 4) * 62;
                        var discard_left = (discardIndex % 4) * 62;
                        discardHtml += this.format_block('jstpl_patrol_tooltip_discard', {
                            discard_left: discard_left,
                            discard_top: discard_top,
                            bg_image: g_gamethemeurl + 'img/patrol.jpg',
                        });
                    }
                }
                discardHtml += '</div>';
            }
            
            return this.format_block('jstpl_patrol_tooltip', {
                patrol_floor: card['location'][5],
                patrol_discards: discardHtml,
                bg_image: g_gamethemeurl + 'img/patrol.jpg',
                bg_position: bg_col.toString() + '% ' + bg_row.toString() + '%'
            });
        },

        drawToolsAndDiscard: function(cards) {
            var dialog = new ebg.popindialog();
            dialog.create( 'drawToolsAndDiscardDialog' );
            dialog.setTitle( _("Choose a Card to Keep") );
            dialog.hideCloseIcon();
            
            var html = this.format_block('jstpl_draw_tools_dialog', {});
            
            dialog.setContent( html ); // Must be set before calling show() so that the size of the content is defined before positioning the dialog

            var tools_stock = new ebg.stock();
            tools_stock.create(this, $('draw_tools_stock'), this.cardwidth * 2, this.cardheight * 2);
            tools_stock.image_items_per_row = 2;
            tools_stock.setSelectionMode(1);
            tools_stock.setSelectionAppearance('class');
            this.addCardTypesToStock(tools_stock, [1]);
            this.loadPlayerHand(tools_stock, cards, [], true);
            
            dialog.show();
            
            // Now that the dialog has been displayed, you can connect your method to some dialog elements
            // Example, if you have an "OK" button in the HTML of your dialog:
            dojo.connect( $('draw_tools_keep_button'), 'onclick', this, function(evt) {
                evt.preventDefault();
                var selected = tools_stock.getSelectedItems();
                if (selected.length == 0) {
                    this.showMessage(_("You must select a tool to keep"), 'error');
                } else {
                    this.handleKeepToolButton(selected[0].id, function() {
                        tools_stock.destroy();
                        dialog.destroy();
                    });
                }
            });
        },

        createDice: function(type, token_type, rolls, bStethoscope = false) {
            // Do not display dice if player preference is No
            if (!bStethoscope && this.prefs[100].value == 2)
                return;
            if (bStethoscope) {
                $('temp_notify').innerHTML = '';
                var rolls_id = 'rolled_dice_stethoscope';
                var container = 'maintitlebar_content';
            } else {
                var rolls_id = 'rolled_dice_' + ++this.diceRolls;
                var container = 'temp_notify';
            }
            dojo.place(this.format_block('jstpl_notification', {
                    id : rolls_id,
                }), container);
            // Add a token before the dice to illustrate the dice roll
            if (token_type in this.gamedatas.token_types) {
                var token = {
                    id: 'die_token',
                    type: token_type,
                    letter: '' // not used
                }
                this.createGenericToken(token, rolls_id);
            }
            // Create each die result icon
            for (id in rolls) {
                dojo.place(this.format_block('jstpl_die', {
                    die_id : 'die_' + id + '_' + rolls[id],
                    die_value : rolls[id],
                    die_color : this.diceColors[type],
                }), rolls_id);
            }
            var wrapper = $(rolls_id).parentNode;
            this.displayElement(wrapper);
            if (!bStethoscope) {
                // Show the close button and binnd the hiding event
                var close_button = wrapper.querySelector('.close_button');
                dojo.removeClass(close_button, 'hidden');
                dojo.connect(close_button, 'onclick', this, function(evt){
                    evt.preventDefault();
                    this.hideElement(wrapper);
                    this.fadeOutAndDestroy(wrapper);  
                } );
                // Hide the dice after a few seconds
                this.displayDiceTimeout = setTimeout( dojo.hitch(this, function() { 
                    if (wrapper) {
                        this.hideElement(wrapper);
                        this.fadeOutAndDestroy(wrapper);                        
                    }
                }), 5000 );
            }
        },
        setupStethoscope: function(rolls) {
            clearTimeout(this.displayDiceTimeout);
            for (id in rolls) {
                this.connect($('die_' + id + '_' + rolls[id]), 'onclick', 'selectDie');
            }
            dojo.place( '<div style="display:block;position:relative;width:100%;" id="dice_choice" class="hidden_animated rolled_dice"></div>', 'rolled_dice_stethoscope')
            for (var i = 1; i <= 6; i++) {
                dojo.place(this.format_block('jstpl_die', {
                    die_id : 'alternative_die_' + i,
                    die_value : i,
                    die_color : 'grey',
                }), 'dice_choice');
                this.connect($('alternative_die_' + i), 'onclick', 'handleMultipleIdCardChoiceButton');
            }
        },
        selectDie: function(e) {
            // console.log('selectDie',e);
            dojo.query('.icon_die.selected').removeClass('selected');
            dojo.addClass(e.target, 'selected');
            this.displayElement('dice_choice');
        },

        setupCrystalBallCards: function(event_cards) {
            this.crystalBallUsed = false;
            for (var i in event_cards) {
                var card = event_cards[i];
                dojo.place( this.eventCardHtml(card, '_' + card.id, 'crystal_ball_card', true), 'crystal_ball_cards' );
                this.addCardTooltip(card, 'event_card_dialog' + card.id);
            }
            if (this.isCurrentPlayerActive()) {
                this.connectClass('crystal_ball_card', 'onclick', 'toggleCardSelection');
            }
            this.displayElement('temp_display');
            dojo.removeClass('crystal_ball_wrapper', 'hidden');
        },
        toggleCardSelection (e) {
            var is_toggle = dojo.hasClass(e.target, 'selected');
            dojo.query('#crystal_ball_cards .selected').removeClass('selected');
            dojo.query('#crystal_ball_cards .vertical_arrow').forEach( (node) => dojo.destroy(node) );
            if ( !is_toggle ) {
                dojo.addClass(e.target, 'selected');
                var index = Array.from(e.target.parentNode.children).indexOf(e.target);
                if (index < 2 )
                    dojo.place( '<div id="move_after" class="vertical_arrow">&#x25B6;</div>', e.target, 'after' );
                if (index > 0)
                    dojo.place( '<div id="move_before" class="vertical_arrow">&#x25C0;</div>', e.target, 'before' );
                this.connectClass('vertical_arrow', 'onclick', 'moveEventCard');
            }
        },
        moveEventCard(e) {
            this.crystalBallUsed = true;
            var container = e.target.parentNode;
            var previous_card = e.target.previousElementSibling;
            var next_card = e.target.nextElementSibling;
            container.insertBefore(next_card, previous_card);
            dojo.query('#crystal_ball_cards .vertical_arrow').forEach( (node) => dojo.destroy(node) );
            dojo.query('#crystal_ball_cards .selected').removeClass('selected');
        },
        displayElement: function(id) {
            $(id).style.maxHeight = '1500px';
            $(id).style.opacity = 1;
        },
        hideElement: function(id) {
            $(id).style.maxHeight = '0px';
            $(id).style.opacity = 0;
        },

        updatePreference: function(prefId, newValue) {
            // Select preference value in control:
            dojo.query('#preference_control_' + prefId + ' > option[value="' + newValue
            // Also select fontrol to fix a BGA framework bug:
                + '"], #preference_fontrol_' + prefId + ' > option[value="' + newValue
                + '"]').forEach((value) => dojo.attr(value, 'selected', true));
            // Generate change event on control to trigger callbacks:
            const newEvt = document.createEvent('HTMLEvents');
            newEvt.initEvent('change', false, true);
            $('preference_control_' + prefId).dispatchEvent(newEvt);
        },

        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        randomizeWalls: function(floor) {
            if (this.checkAction('randomizeWalls')) {
                this.ajaxcall('/burglebros/burglebros/randomizeWalls.html', { lock: true, floor: floor }, this, console.log, console.error);
            }
        },

        handleTileClick: function(evt, id) {
            // console.log('handleTileClick', evt, id);
            // console.log("this.gamedatas.gamestate.name", this.gamedatas.gamestate.name);
            dojo.stopEvent(evt);

            if (this.isCardChoice('crystal-ball'))
                return;
            if (this.gamedatas.gamestate.name == 'cardChoice' && this.checkAction('selectCardChoice')) {
                var selected_type = 'tile', selected_id = id;
                if (dojo.hasClass(evt.target, 'meeple')) {
                    selected_type = 'meeple';
                    selected_id = evt.target.id.substring(evt.target.id.lastIndexOf('_') + 1);
                }
                this.ajaxcall('/burglebros/burglebros/selectCardChoice.html', { lock: true, selected_type: selected_type, selected_id: selected_id }, this, console.log, console.error);
            } else if(this.gamedatas.gamestate.name == 'startingTile' && this.checkAction('chooseStartingTile')) {
                this.ajaxcall('/burglebros/burglebros/chooseStartingTile.html', { lock: true, id: id }, this, console.log, console.error);
            } else if(this.gamedatas.gamestate.name == 'playerChoice' && dojo.hasClass(evt.target, 'meeple') && this.checkAction('selectPlayerChoice')) {
                var player_id = evt.target.id.substring(evt.target.id.lastIndexOf('_') + 1);
                this.ajaxcall('/burglebros/burglebros/selectPlayerChoice.html', { lock: true, selected: player_id }, this, console.log, console.error);
            } else if(this.gamedatas.gamestate.name == 'specialChoice' && dojo.hasClass(evt.target, 'tile') && this.checkAction('selectSpecialChoice')) {
                this.ajaxcall('/burglebros/burglebros/selectSpecialChoice.html', { lock: true, selected: id }, this, console.log, console.error);
            } else if(this.gamedatas.gamestate.name == 'specialChoice' && (dojo.hasClass(evt.target, 'tile-meeples') || dojo.hasClass(evt.target, 'tile-tokens')) && this.checkAction('selectSpecialChoice')) {
                // Handle front edge case when player clicks on meeple or token zone instead of tile zone, find the tile div
                id = $(evt.target.id).parentNode.querySelectorAll('.tile')[0].id.split('_')[1];
                this.ajaxcall('/burglebros/burglebros/selectSpecialChoice.html', { lock: true, selected: id }, this, console.log, console.error);
            } else if(this.gamedatas.gamestate.name == 'specialChoice' && dojo.hasClass(evt.target, 'token') && this.checkAction('selectSpecialChoice')) {
                // Handle front edge case when player clicks on a token zone instead of tile zone, find the tile div
                id = $(evt.target.id).parentNode.parentNode.querySelectorAll('.tile')[0].id.split('_')[1];
                this.ajaxcall('/burglebros/burglebros/selectSpecialChoice.html', { lock: true, selected: id }, this, console.log, console.error);
            } else {
                var intent = this.intent || 'default';
                var action = intent == 'default' ? 'move' : intent;
                if (this.checkAction(action)) {
                    var url = '/burglebros/burglebros/' + action + '.html';
                    var context = 'action';
                    // If acrobat is moving onto a guard, ask if player wants to use the special ability
                    var tile_has_guard = $(evt.target.id).parentNode.querySelectorAll('.token.guard').length > 0;
                    // Multicharacters may not be the Acrobat :)
                    if (this.gamedatas.solo_characters > 1) {
                        var active_player_id = this.gamedatas.active_player_id;
                        var is_acrobat = this.gamedatas['players'][active_player_id].character.name == 'acrobat1';
                    } else {
                        var is_acrobat = this.gamedatas.gamestate.args.character.name == 'acrobat1';
                    }
                    if ( (intent == 'move' || intent == 'default') && is_acrobat && tile_has_guard ) {
                        this.multipleChoiceDialog(
                          _('Do you want to use your Acrobat ability?'), [_('Yes'), _('No'), _('Cancel move')], 
                            dojo.hitch(this, function(choice) {
                                if (choice != 2) {
                                    context = choice == 0 ? 'acrobat1' : context;
                                    this.ajaxcall( url, { lock: true, id: id, context: context }, this, function( result ) {} );
                                }
                        }));
                    } else {
                        if (intent == 'default') {
                            if (this.prefs[101].value == 1) {
                                this.multipleChoiceDialog(
                                    _('Do you really want to move?') + '<br>' + _('You can change this dialog appearance in your game options.'), [_('Yes, only this time'), _('Yes, and never ask again'), _('No')], 
                                    dojo.hitch(this, function(choice) {
                                        // Check that player wants to move
                                        if (choice < 2) {
                                            // Change player option to remove this confirmation dialog
                                            if (choice == 1) {
                                                this.updatePreference(101, 2);
                                            }
                                            this.ajaxcall( url, { lock: true, id: id, context: context }, this, function( result ) {} );                                        
                                        }
                                }));
                            } else {
                                this.ajaxcall( url, { lock: true, id: id, context: context }, this, function( result ) {} );   
                            }
                        } else {
                            // Normal move
                            this.ajaxcall(url, { lock: true, id: id, context: context }, this, function() {
                                console.log('success', arguments);
                            }, function() {
                                console.log('error', arguments);
                                if (intent == 'peek' && arguments[0]) {
                                    // Reset intent to Peek after error
                                    this.intent = 'peek';
                                }
                            });
                        }
                    }
                    this.intent = 'default';
                }
            }
        },

        handleEscape: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('escape')) {
                this.ajaxcall('/burglebros/burglebros/escape.html', { lock: true }, this, console.log, console.error);
            }
        },

        handlePeekClick: function(evt) {
            dojo.stopEvent(evt);
            this.intent = 'peek';
            this.changeMainBar('Select an adjacent tile to peek');
            this.addActionButton('button_cancel', _('Cancel'), 'handleCancelClick');
        },

        handleMoveClick: function(evt) {
            dojo.stopEvent(evt);
            this.intent = 'move';
            this.changeMainBar('Select an adjacent tile to move to');
            this.addActionButton('button_cancel', _('Cancel'), 'handleCancelClick');
        },

        handleCancelClick: function(evt) {
            dojo.stopEvent(evt);
            this.intent = 'default';
            this.updatePageTitle();
        },

        handleAddSafeDie: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('addSafeDie')) {
                this.ajaxcall('/burglebros/burglebros/addSafeDie.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleRollSafeDice: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('rollSafeDice')) {
                this.ajaxcall('/burglebros/burglebros/rollSafeDice.html', { lock: true }, this, function() {
                    console.log(arguments);
                    // location.reload();
                }, console.error);
            }
        },

        handleHack: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('hack')) {
                this.ajaxcall('/burglebros/burglebros/hack.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleTrade: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('trade')) {
                this.ajaxcall('/burglebros/burglebros/trade.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleTakeCards: function(evt) {
            console.log("handleTakeCards", evt);
            dojo.stopEvent(evt);
            if (this.checkAction('takeCards')) {
                this.ajaxcall('/burglebros/burglebros/takeCards.html', { lock: true }, this, console.log, console.error);
            }
        },

        handlePickUpCat: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('pickUpCat')) {
                this.ajaxcall('/burglebros/burglebros/pickUpCat.html', { lock: true }, this, console.log, console.error);
            }
        },

        handlePassClick: function(evt) {
            dojo.stopEvent(evt);
            if (this.checkAction('pass')) {
                if (this.actionsRemaining() >= 2) {
                    this.confirmationDialog( _('Are you sure you want to pass? This action may trigger an event'), dojo.hitch( this, function() {
                        this.ajaxcall('/burglebros/burglebros/pass.html', { lock: true }, this, function() {
                            console.log(arguments);
                        }, console.error);
                    } ) );
                } else {
                    this.ajaxcall('/burglebros/burglebros/pass.html', { lock: true }, this, function() {
                        console.log(arguments);
                    }, console.error);
                } 
            }
        },

        handleRestartTurnClick: function(evt)  {
            dojo.stopEvent(evt);
            if (this.checkAction('restartTurn')) {
                this.ajaxcall('/burglebros/burglebros/restartTurn.html', { lock: true }, this, function() {
                    console.log(arguments);
                }, console.error);
            }
        },

        handleCardSelected: function(control_name, card_id) {
            // console.log("handleCardSelected", control_name, card_id);
            var handStock = this.getHandStock();
            if (handStock.isSelected(card_id) && this.checkAction('playCard')) {
                this.ajaxcall('/burglebros/burglebros/playCard.html', { lock: true, id: card_id }, this, console.log, console.error);
            } else if (!handStock.isSelected(card_id)) {
                this.handleCancelCardChoice();
            }
        },

        handleCancelCardChoice: function() {
            // console.log('cancelCardChoice');
            if (this.checkAction('cancelCardChoice')) {
                var stethoscope = $('wrapper_rolled_dice_stethoscope');
                if (stethoscope) {
                    dojo.destroy(stethoscope);
                }
                this.myHand.unselectAll();
                this.ajaxcall('/burglebros/burglebros/cancelCardChoice.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleMultipleIdCardChoiceButton: function(e) {
            this.ids = [];
            if (this.isCardChoice('crystal-ball')) {
                dojo.query('#crystal_ball_cards .crystal_ball_card').forEach( (node) => this.ids.push(node.id.split("_").pop()) );
                if (!this.crystalBallUsed) {
                    this.confirmationDialog( _('You didn\'t change the event order, do you want to keep them this way?'), dojo.hitch( this, function() {
                        this.handleCardChoiceButton(this.ids.join(";"), null);
                    } ) );
                    return;
                }
            } else if (this.isCardChoice('stethoscope')) {
                // Push old value first then new value
                this.ids.push( dojo.query('.icon_die.selected')[0].id.split('_').pop() );
                this.ids.push( e.target.id.split('_').pop() );
                this.hideElement('wrapper_rolled_dice_stethoscope');
                this.fadeOutAndDestroy('wrapper_rolled_dice_stethoscope');
            }
            this.handleCardChoiceButton(this.ids.join(";"), null);
        },

        handleCardChoiceButton: function(id, callback) {
            callback = typeof callback == 'object' ? null : callback;
            if (this.checkAction('selectCardChoice')) {
                this.ajaxcall('/burglebros/burglebros/selectCardChoice.html', { lock: true, selected_type: 'button', selected_id: id }, this, callback || console.log, console.error);
            }
        },

        handleTileChoiceButton: function(selected) {
            console.log("handleTileChoiceButton", selected);
            if (this.checkAction('selectTileChoice')) {
                this.ajaxcall('/burglebros/burglebros/selectTileChoice.html', { lock: true, selected: selected }, this, console.log, console.error);
            }
        },

        handleCharacterAction: function() {
            console.log("handleCharacterAction");
            if (this.checkAction('characterAction')) {
                this.ajaxcall('/burglebros/burglebros/characterAction.html', { lock: true }, this, console.log, console.error);
            }
        },

        handlePlayerChoice: function(player_id) {
            console.log("handlePlayerChoice", player_id);
            if (this.checkAction('selectPlayerChoice')) {
                this.ajaxcall('/burglebros/burglebros/selectPlayerChoice.html', { lock: true, selected: player_id }, this, console.log, console.error);
            }
        },

        handleConfirmRookMove: function() {
            console.log("handleConfirmRookMove");
            if (this.checkAction('confirmRookMove')) {
                this.ajaxcall('/burglebros/burglebros/confirmRookMove.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleCancelRookMove: function() {
            console.log("handleCancelRookMove");
            if (this.checkAction('cancelRookMove')) {
                this.ajaxcall('/burglebros/burglebros/cancelRookMove.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleCancelPlayerChoice: function() {
            if (this.checkAction('cancelPlayerChoice')) {
                this.ajaxcall('/burglebros/burglebros/cancelPlayerChoice.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleCancelTrade: function() {
            if (this.checkAction('cancelTrade')) {
                this.ajaxcall('/burglebros/burglebros/cancelTrade.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleCancelTakeCards: function() {
            if (this.checkAction('cancelTakeCards')) {
                this.ajaxcall('/burglebros/burglebros/cancelTakeCards.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleCancelSpecialChoice: function() {
            if (this.checkAction('cancelSpecialChoice')) {
                this.ajaxcall('/burglebros/burglebros/cancelSpecialChoice.html', { lock: true }, this, console.log, console.error);
            }
        },

        handleKeepToolButton: function(id, callback) {
            if (this.checkAction('keepTool')) {
                this.ajaxcall('/burglebros/burglebros/keepTool.html', { lock: true, selected: id }, this, callback || console.log, console.error);
            }
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your burglebros.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            dojo.subscribe('characterChosen', this, 'notif_characterChosen');
            dojo.subscribe('activatePlayer', this, 'notif_activatePlayer');
            dojo.subscribe('tokensPicked', this, 'notif_tokensPicked');
            dojo.subscribe('tokensPickedSync', this, 'notif_tokensPicked');
            dojo.subscribe('catEscaped', this, 'notif_catEscaped');
            dojo.subscribe('catPicked', this, 'notif_catPicked');
            this.notifqueue.setSynchronous( 'tokensPickedSync', 750 );
            dojo.subscribe('decrementStealth', this, 'notif_decrementStealth');
            dojo.subscribe('tileFlipped', this, 'notif_tileFlipped');
            dojo.subscribe('nextPatrol', this, 'notif_nextPatrol');
            dojo.subscribe('createGuardPath', this, 'notif_createGuardPath');
            dojo.subscribe('updateGuardPath', this, 'notif_updateGuardPath');
            dojo.subscribe('playerHand', this, 'notif_playerHand');
            dojo.subscribe('addTooltipToLog', this, 'notif_addTooltipToLog');
            dojo.subscribe('eventCard', this, 'notif_eventCard');
            dojo.subscribe('safeDieIncreased', this, 'notif_safeDieIncreased');
            dojo.subscribe('diceRolled', this, 'notif_diceRolled');
            dojo.subscribe('patrolDieIncreased', this, 'notif_patrolDieIncreased');
            dojo.subscribe('tileCards', this, 'notif_tileCards');
            dojo.subscribe('showFloor', this, 'notif_showFloor');
            dojo.subscribe('updateWalls', this, 'notif_updateWalls');
            dojo.subscribe('removeWall', this, 'notif_removeWall');
            dojo.subscribe('playerEscape', this, 'notif_playerEscape');
        },

        /** Override this function to inject html into log items. This is a built-in BGA method.
            Described here https://en.doc.boardgamearena.com/BGA_Studio_Cookbook#Inject_images_and_styled_html_in_the_log */
        /* @Override */
        format_string_recursive : function(log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;
                    // Building name contains building material id that we want to replace with building name and tooltip
                    if ('card_id' in args) {
                        console.log("*** found card_id", args);
                        args['title'] = '<span id="log_' + args['card_id'] + '" class="card_name">' + args['title'] + '</span>';
                    }
                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },

        notif_characterChosen: function(notif) {
            console.log('** notif_characterChosen', notif.args);
            this.gamedatas.players[notif.args.player_id].character = notif.args.character;
        },

        notif_activatePlayer: function(notif) {
            // Solo multicharacters, change layout to display active character first
            console.log('** notif_activatePlayer', notif.args);
            this.activatePlayer(notif.args.player_id);
        },

        notif_tokensPicked: function(notif) {
            console.log('** notif_tokensPicked', notif.args);
            var tokens = notif.args.tokens;
            for (var tokenId in tokens) {
                var token = tokens[tokenId];
                var isGeneric = this.nonGenericTokenTypes.indexOf(token.type) == -1;
                var type = isGeneric ? 'generic' : token.type;
                // Force refresh of gamedatas (at least needed for escaped player token)
                // this.gamedatas[type + '_tokens'][tokenId] = token;
                this.removeToken(type, tokenId);
                if (isGeneric) {
                    delete this.gamedatas.generic_tokens[tokenId];
                }
                // console.log('this.canMoveToken(token)', this.canMoveToken(token));
                if (this.canMoveToken(token)) {
                    if (isGeneric) {
                        this.createGenericToken(token);
                    }
                    if (token.floor) {
                        // console.log("notif_tokensPicked token.floor", token.floor);
                        this.showFloor(token.floor);
                    }
                    this.moveToken(type, token);
                    this.gamedatas[type + '_tokens'][tokenId] = token;
                }
            }
        },
        notif_catEscaped: function(notif) {
            this.catEscaped();
        },
        notif_catPicked: function(notif) {
            // $("card_kitty_warning").innerHTML("");
            dojo.empty("card_kitty_warning");
        },

        notif_decrementStealth: function(notif) {
            console.log('notif_decrementStealth', notif.args);
            var meeple_id = notif.args.meeple_id;
            // Display animation on meeple when gaining or losing Stealth token
            dojo.addClass('meeple_' + meeple_id, 'ripple');
            setTimeout(function () {
                dojo.removeClass('meeple_' + meeple_id, 'ripple');
            }, 3000)
        },

        notif_tileFlipped: function(notif) {
            console.log("notif_tileFlipped", notif.args);
            var tile = notif.args.tile,
                floor = tile.location[5];
                deck = 'floor' + floor;
            this.gamedatas[deck][tile.location_arg] = tile;
            this.gamedatas.flipped_tiles = notif.args.flipped_tiles;
            this.showFloor(floor);
            this.playTileOnTable(floor, tile);
            this.undo_allowed = notif.args.undo_allowed;
            if (this.undo_allowed == 0 && $('button_undo') != null) {
                this.fadeOutAndDestroy('button_undo');
            }
        },

        notif_nextPatrol: function(notif) {
            console.log('notif next patrol', notif.args);
            var deck = 'patrol' + notif.args.floor + '_discard';
            var floor = notif.args.floor;
            this.gamedatas[deck] = notif.args.cards;
            this.gamedatas[deck + '_top'] = notif.args.top;
            this.showFloor(floor);
            this.loadPatrolDiscard(floor, notif.args.top);
            this.patrolCounters[floor].toValue(notif.args.deck_count);
        },

        notif_createGuardPath: function(notif) {
            console.log('notif_createGuardPath', notif.args);
            var floor = notif.args.floor;
            this.gamedatas.guard_paths['floor' + floor] = notif.args.path;
            this.createGuardPath(floor, this.gamedatas.guard_paths['floor' + floor]);
        },

        notif_updateGuardPath: function(notif) {
            console.log('notif_updateGuardPath', notif.args);
            var floor = notif.args.floor;
            this.gamedatas.guard_paths['floor' + floor] = notif.args.path;
            this.updateGuardPath(floor, this.gamedatas.guard_paths['floor' + floor], notif.args.position);
        },
        
        notif_playerHand: function(notif) {
            console.log('notif_playerHand', notif.args);
            var hand = notif.args.hand;
            var playerId = notif.args.player_id;
            this.gamedatas.players[playerId].hand = hand;
            var handStock = playerId == this.player_id ? this.myHand : this.playerHands[playerId];
            this.loadPlayerHand(handStock, hand, notif.args.discard_ids, false);
        },

        notif_addTooltipToLog: function(notif) {
            this.addCardTooltip(notif.args.card, 'log_' + notif.args.card.id);
        },

        notif_eventCard: function(notif) {
            var event_card = notif.args.card;
            var dialog = new ebg.popindialog();
            dialog.create( 'eventCardDialog' );
            dialog.setTitle( _("Event Card") );
            
            // Show the dialog
            dialog.setContent( this.eventCardHtml(event_card) ); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
            dialog.show();
            
            // Now that the dialog has been displayed, you can connect your method to some dialog elements
            // Example, if you have an "OK" button in the HTML of your dialog:
            // dojo.connect( $('my_ok_button'), 'onclick', this, function(evt){
            //     evt.preventDefault();
            //     dialog.destroy();
            // } );
            // Add tooltip to the notification log
            this.addCardTooltip(event_card, 'log_' + event_card.id);
        },

        notif_safeDieIncreased: function(notif) {
            this.createSafeToken(notif.args.token, notif.args.die_num);
            console.log("notif_safeDieIncreased", notif.args);
            this.showFloor(notif.args.floor);
        },

        notif_diceRolled: function(notif) {
            console.log("notif_diceRolled", notif.args);
            var type = notif.args.for;
            var token_type = type;
            switch(type) {
                case 'persian-kitty':
                    token_type = 'cat';
                    break;
                case 'chihuahua':
                    token_type = 'alarm';
                    break;
            }
            // console.log("type", type);
            var rolls = notif.args.rolls;
            this.createDice(type, token_type, rolls, false);
        },

        notif_patrolDieIncreased: function(notif) {
            this.createPatrolToken(notif.args.token, notif.args.die_num);
            console.log("notif_patrolDieIncreased", notif.args);
            this.showFloor(notif.args.floor);
        },

        notif_tileCards: function(notif) {
            var tile_id = notif.args.tile_id;
            var token = notif.args.tokens[tile_id];
            this.destroyCardToken(tile_id);
            if (token) {
                this.createCardToken(tile_id, token.type, token.count, '');
            }
        },

        notif_showFloor: function(notif) {
            console.log("notif_showFloor", notif.args);
            this.showFloor(notif.args.floor);
        },

        notif_updateWalls: function(notif) {
            console.log("notif_updateWalls", notif.args);
            // Remove only selected floor
            if (notif.args.floor === 'all') {
                dojo.query('.floor .wall').forEach(dojo.destroy);
            } else {
                dojo.query('#floor' + notif.args.floor + ' .wall').forEach(dojo.destroy);
            }
            for (var wallIdx = 0; wallIdx < notif.args.walls.length; wallIdx++) {
                var wall = notif.args.walls[wallIdx];
                this.playWallOnTable(wall);
            }
            // Update tiles to update shaft
            // var tiles = notif.args.tiles;
            // dojo.query(".tile-container").forEach( (e) => dojo.destroy(e) );
            // for (var floor = 1; floor <= this.gamedatas.floor_count; floor++) {
            //     var key = 'floor' + floor;
            //     for ( var tileId in tiles[key]) {
            //         var tile = tiles[key][tileId];
            //         this.createTileContainer(floor, tile);
            //         this.playTileOnTable(floor, tile);
            //     }
            // }
        },

        notif_removeWall: function(notif) {
            this.fadeOutAndDestroy($("wall_" + notif.args.wall_id));
        },

        notif_playerEscape: function(notif) {
            console.log('notif_playerEscape', notif.args);
            var player_id = notif.args.player_id;
            this.playerEscaped(player_id);
            this.fadeOutAndDestroy('meeple_' + notif.args.token_id);
        },
   });             
});
