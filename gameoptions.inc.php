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
 * gameoptions.inc.php
 *
 * burglebros game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in burglebros.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

    /*
    
    // note: game variant ID should start at 100 (ie: 100, 101, 102, ...). The maximum is 199.
    100 => array(
                'name' => totranslate('my game option'),    
                'values' => array(

                            // A simple value for this option:
                            1 => array( 'name' => totranslate('option 1') )

                            // A simple value for this option.
                            // If this value is chosen, the value of "tmdisplay" is displayed in the game lobby
                            2 => array( 'name' => totranslate('option 2'), 'tmdisplay' => totranslate('option 2') ),

                            // Another value, with other options:
                            //  description => this text will be displayed underneath the option when this value is selected to explain what it does
                            //  beta=true => this option is in beta version right now.
                            //  nobeginner=true  =>  this option is not recommended for beginners
                            3 => array( 'name' => totranslate('option 3'), 'description' => totranslate('this option does X'), 'beta' => true, 'nobeginner' => true )
                        )
            )

    */
    100 => array(
        'name' => totranslate('Character Assignment'),    
        'values' => array(

                    // A simple value for this option:
                    1 => array(
                        'name' => totranslate('Random, no advanced'),
                        'description' => totranslate('Random character assignment, no advanced versions')
                    ),
                    2 => array(
                        'name' => totranslate('Random, w/advanced'),
                        'description' => totranslate('Random character assignment, including advanced versions, each player can choose either basic or advanced side')
                    ),

                    3 => array(
                        'name' => totranslate('Player choice, no advanced'),
                        'description' => totranslate('Each player can choose a character, no advanced versions')
                    ),
                    4 => array(
                        'name' => totranslate('Player choice, w/advanced'),
                        'description' => totranslate('Each player can choose a character, including advanced versions, each player can choose either basic or advanced side')
                    ),

                    // Another value, with other options:
                    //  description => this text will be displayed underneath the option when this value is selected to explain what it does
                    //  beta=true => this option is in beta version right now.
                    //  nobeginner=true  =>  this option is not recommended for beginners
                    // 3 => array( 'name' => totranslate('option 3'), 'description' => totranslate('this option does X'), 'beta' => true, 'nobeginner' => true )
                )
    ),

    101 => array(
        'name' => totranslate('Level'),    
        'values' => array(
                    1 => array(
                        'name' => totranslate('Easy'),
                        'description' => totranslate('Each player starts with 6 stealth tokens')
                    ),
                    2 => array(
                        'name' => totranslate('Normal'),
                        'description' => totranslate('Each player starts with 3 stealth tokens')
                    ),
                    3 => array(
                        'name' => totranslate('Hard'),
                        'description' => totranslate('Each player starts with 1 stealth token')
                    ),

                ),
        'default' => 2,
    ),

    102 => array(
        'name' => totranslate('Scenario'),    
        'values' => array(
                    1 => array(
                        'name' => totranslate('The Bank Job'),
                        'description' => totranslate('Standard layout (3 floors of 4x4 grid)')
                    ),
                    2 => array(
                        'name' => totranslate('The Office Job'),
                        'description' => totranslate('Beginners\' layout (2 floors of 4x4 grid)')
                    ),
                    3 => array(
                        'name' => totranslate('The Fort Knox Job'),
                        'description' => totranslate('Veterans\' layout (2 floors of 5x5 grid)')
                    ),
                ),
        'default' => 1,
    ),

    103 => array(
        'name' => totranslate('Walls'),    
        'values' => array(
                    1 => array(
                        'name' => totranslate('Default walls'),
                        // 'description' => totranslate('')
                    ),
                    2 => array(
                        'name' => totranslate('Random walls'),
                        'description' => totranslate('Walls are generated randomly, table admin can reroll')
                    ),
                ),
        'default' => 1,
    )
);


