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
                        'tmdisplay' => totranslate('Basic random characters'),
                        'description' => totranslate('Random character assignment, no advanced versions')
                    ),
                    2 => array(
                        'name' => totranslate('Random, w/advanced'),
                        'tmdisplay' => totranslate('Advanced random characters'),
                        'description' => totranslate('Random character assignment, including advanced versions, each player can choose either basic or advanced side')
                    ),

                    3 => array(
                        'name' => totranslate('Player choice, no advanced'),
                        'tmdisplay' => totranslate('Basic characters choice'),
                        'description' => totranslate('Each player can choose a character, no advanced versions')
                    ),
                    4 => array(
                        'name' => totranslate('Player choice, w/advanced'),
                        'tmdisplay' => totranslate('Advanced characters choice'),
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
                        'tmdisplay' => totranslate('Easy'),
                        'description' => totranslate('Each player starts with 6 stealth tokens')
                    ),
                    2 => array(
                        'name' => totranslate('Normal'),
                        'description' => totranslate('Each player starts with 3 stealth tokens')
                    ),
                    3 => array(
                        'name' => totranslate('Hard'),
                        'tmdisplay' => totranslate('Hard'),
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
                        'tmdisplay' => totranslate('The Bank Job'),
                        'description' => totranslate('Standard layout (3 floors of 4x4 grid)')
                    ),
                    2 => array(
                        'name' => totranslate('The Office Job'),
                        'tmdisplay' => totranslate('The Office Job'),
                        'description' => totranslate('Beginners\' layout (2 floors of 4x4 grid)')
                    ),
                    3 => array(
                        'name' => totranslate('The Fort Knox Job'),
                        'tmdisplay' => totranslate('The Fort Knox Job'),
                        'description' => totranslate('Veterans\' layout (2 floors of 5x5 grid)'),
                        'nobeginner' => true // this option is not recommended for beginners
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
                        'tmdisplay' => totranslate('Random walls'),
                        'description' => totranslate('Walls are generated randomly')
                    ),
                ),
        'default' => 1,
    ),

    104 => array(
        'name' => totranslate('Solo multi-characters'),    
        'values' => array(
            1 => array( 
                'name' => totranslate('--- Play only one character ---'),
            ),
            2 => array( 
                'name' => totranslate('Play 2 characters')
            ),
            3 => array(
                'name' => totranslate('Play 3 characters'),
            ),
            4 => array(
                'name' => totranslate('Play 4 characters'),
            ),
        ),
        'default' => 1,
        'displaycondition' => array(
             // Note: do not display this option unless these conditions are met
             array(
                 'type' => 'maxplayers',
                 'value' => 1,
                 'message' => totranslate('Solo variants can be played at 1 player only.')
             ),
        ),
        'notdisplayedmessage' => totranslate('Solo variants are only available with 1 player')
    ),

    // 105 would be a wrapper for all the house rules if more than 1
    106 => array(
        'name' => totranslate('Deadbolts distribution'),    
        'values' => array(
            1 => array( 
                'name' => totranslate('Fully random'),
            ),
            2 => array( 
                'name' => totranslate('1 deadbolt per floor'),
                'description' => totranslate('Put 1 deadbolt on each floor for The Bank Job and The Office Job and max 2 deadbolts per floor for the Fort Knox Job'),
                 'beta' => true  // This option is in beta version right now.
            ),
        ),
        'default' => 1,
    ),
);

$game_preferences = array(
    100 => array(
            'name' => totranslate('Display dice result'),
            'needReload' => false,
            'values' => array(
                1 => array( 
                    'name' => totranslate( 'Yes' ),
                    'description' => totranslate('Show the dice result on top of the window (safe, keypad, chihuahua, Persian Cat')
                ),
                2 => array( 
                    'name' => totranslate( 'No' ),
                    'description' => totranslate('Show the dice result only in the logs')  
                )
            )
    ),
    102 => array(
            'name' => totranslate('Auto hide dice result'),
            'needReload' => true,
            'values' => array(
                5 => array( 
                    'name' => totranslate( 'After 5 seconds' ),
                    'description' => totranslate('Automatically hide the die results after 5 seconds')
                ),
                4 => array( 
                    'name' => totranslate( 'After 4 seconds' ),
                    'description' => totranslate('Automatically hide the die results after 4 seconds')
                ),
                3 => array( 
                    'name' => totranslate( 'After 3 seconds' ),
                    'description' => totranslate('Automatically hide the die results after 3 seconds')
                ),
                2 => array( 
                    'name' => totranslate( 'After 2 seconds' ),
                    'description' => totranslate('Automatically hide the die results after 2 seconds')
                ),
                1 => array( 
                    'name' => totranslate( 'After 1 second' ),
                    'description' => totranslate('Automatically hide the die results after 1 second')
                ),
                0 => array( 
                    'name' => totranslate( 'Never' ),
                    'description' => totranslate('Click on the X button to close the dialog')  
                )
            ),
            'default' => 5,
    ),
    101 => array(
            'name' => totranslate('Confirm meeple move'),
            'needReload' => true,
            'values' => array(
                1 => array( 
                    'name' => totranslate( 'Each time' ),
                    'description' => totranslate('The game will ask you to confirm your move if you do not use the action button')
                ),
                2 => array( 
                    'name' => totranslate( 'Never' ),
                    'description' => totranslate('You can click on an adjacent tile to move directly your meeple')  
                )
            )
    ),
    103 => array(
            'name' => totranslate('Auto switch to active floor'),
            'needReload' => true,
            'values' => array(
                0 => array( 
                    'name' => totranslate( 'Immediately' ),
                    'description' => totranslate('Switch floor right after the previous action')  
                ),
                1 => array( 
                    'name' => totranslate( 'After 1 second' ),
                    'description' => totranslate('Wait for 1 second before switching floor')
                ),
                2 => array( 
                    'name' => totranslate( 'After 2 seconds' ),
                    'description' => totranslate('Wait for 2 seconds before switching floor')
                ),
                3 => array( 
                    'name' => totranslate( 'After 3 seconds' ),
                    'description' => totranslate('Wait for 3 seconds before switching floor')
                ),
                4 => array( 
                    'name' => totranslate( 'After 4 seconds' ),
                    'description' => totranslate('Wait for 4 seconds before switching floor')
                ),
                5 => array( 
                    'name' => totranslate( 'After 5 seconds' ),
                    'description' => totranslate('Wait for 5 seconds before switching floor')
                ),
                99 => array( 
                    'name' => totranslate( 'Never' ),
                    'description' => totranslate('You can change manually floor view')
                )
            ),
            'default' => 1,
    ),
    104 => array(
        'name' => totranslate('Show row and column indicator'),
        'needReload' => true,
        'values' => array(
            1 => array( 
                'name' => totranslate( 'Yes' ),
                'description' => totranslate('Show row and column indicator')
            ),
            2 => array( 
                'name' => totranslate( 'No' ),
                'description' => ''
            )
        ),
        'default' => 2,
    ),
);


