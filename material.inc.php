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
 * material.inc.php
 *
 * burglebros game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->card_types = array(
  CardType::CHARACTER => new DeckType('characters'),
  CardType::TOOL => new DeckType('tools'),
  CardType::LOOT => new DeckType('loot'),
  CardType::EVENT => new DeckType('events'),
);


$this->card_info = array(
  CardType::CHARACTER => array(
    new CardInfo(
      name: 'acrobat1',
      title: clienttranslate('The Acrobat'),
      subhead: clienttranslate('Retired Performer'),
      ability: clienttranslate('Flexibility'),
      choice_description: clienttranslate('an adjacent tile containing a guard'),
      tooltip: clienttranslate('You may move into a tile with a Guard as a free action and you don\'t use a Stealth when you do. You must leave before the Guard moves or lose a Stealth.'),
    ),
    new CardInfo(
      name: 'acrobat2',
      title: clienttranslate('The Acrobat'),
      subhead: clienttranslate('Retired Performer'),
      ability: clienttranslate('Climb Window'),
      choice_description: clienttranslate('an option'),
      tooltip: clienttranslate('If you are on an outer tile, you may spend 3 actions to move up or down 1 floor. This ends your actions.'),
    ),
    new CardInfo(
      name: 'hacker1',
      title: clienttranslate('The Hacker'),
      subhead: clienttranslate('Computer Guy'),
      ability: clienttranslate('Jammer'),
      tooltip: clienttranslate('You do not trigger Fingerprint, Laser or Motion tiles. Other players will not trigger them while you are there.'),
    ),
    new CardInfo(
      name: 'hacker2',
      title: clienttranslate('The Hacker'),
      subhead: clienttranslate('Computer Guy'),
      ability: clienttranslate('Laptop'),
      tooltip: clienttranslate('You can add a hack token to yourself for one action (limit 1). This token can be used as a laser, motion or fingerprint hack token by any player.'),
    ),
    new CardInfo(
      name: 'hawk1',
      title: clienttranslate('The Hawk'),
      subhead: clienttranslate('Recon Pro'),
      ability: clienttranslate('X-Ray'),
      choice_description: clienttranslate('an adjacent tile behind a wall to peek'),
      tooltip: clienttranslate('Once per turn as a free action you may peak at an adjacent tile through a wall.'),
    ),
    new CardInfo(
      name: 'hawk2',
      title: clienttranslate('The Hawk'),
      subhead: clienttranslate('Recon Pro'),
      ability: clienttranslate('Enhance'),
      choice_description: clienttranslate('a tile up to two spaces away to peek'),
      tooltip: clienttranslate('As a free action once per turn, you may peak at a tile up to two spaces away. You may not skip over an unrevealed tile. You cannot see through walls, but you can see around corners and up stairs.'),
    ),
    new CardInfo(
      name: 'juicer1',
      title: clienttranslate('The Juicer'),
      subhead: clienttranslate('Electronics Expert'),
      ability: clienttranslate('Crybaby'),
      choice_description: clienttranslate('an adjacent tile to trigger an alarm'),
      tooltip: clienttranslate('As a free action, you may create an alarm in the adjacent tile (but not through walls).'),
    ),
    new CardInfo(
      name: 'juicer2',
      title: clienttranslate('The Juicer'),
      subhead: clienttranslate('Electronics Expert'),
      ability: clienttranslate('Reroute'),
      tooltip: clienttranslate('Once per turn as a free action, you may pick up an active alarm on your tile and draw a new patrol card OR discard you alarm token to trigger an alarm (limit 1).'),
    ),
    new CardInfo(
      name: 'peterman1',
      title: clienttranslate('The Peterman'),
      subhead: clienttranslate('Safecraker'),
      ability: clienttranslate('Steady Hands'),
      tooltip: clienttranslate('When rolling for the Safe or Keypad, roll 1 additional die.'),
    ),
    new CardInfo(
      name: 'peterman2',
      title: clienttranslate('The Peterman'),
      subhead: clienttranslate('Safecraker'),
      ability: clienttranslate('Drill'),
      choice_description: clienttranslate('up or down to crack safe'),
      tooltip: clienttranslate('You may add dice and roll on safes above or below your tile, but cannot pick up loot or tools from those safes.'),
    ),
    new CardInfo(
      name: 'raven1',
      title: clienttranslate('The Raven'),
      subhead: clienttranslate('Maverick Falconer'),
      ability: clienttranslate('Distract'),
      choice_description: clienttranslate('a tile up to two spaces away to place the crow'),
      tooltip: clienttranslate('As a free action, you may place the crow token up to two tiles away from your character (not through walls). If the guard enters a tile with a crow, he loses one movement. The crow remains in that location until you move it again.'),
    ),
    new CardInfo(
      name: 'raven2',
      title: clienttranslate('The Raven'),
      subhead: clienttranslate('Maverick Falconer'),
      ability: clienttranslate('Disrupt'),
      tooltip: clienttranslate('As a free action, you may place the crow token on your current tile. If the Guard starts his movement on the same tile as the crow, AND there are no alarms, he loses all movement, and the crow is returned to you.'),
    ),
    new CardInfo(
      name: 'rigger1',
      title: clienttranslate('The Rigger'),
      subhead: clienttranslate('Tinkerer Savant'),
      ability: clienttranslate('The Solution'),
      tooltip: clienttranslate('You start with the Dynamite Tool. When any player finds a Tool, they may draw two Tool cards, keep one and discard the other.'),
    ),
    new CardInfo(
      name: 'rigger2',
      title: clienttranslate('The Rigger'),
      subhead: clienttranslate('Tinkerer Savant'),
      ability: clienttranslate('Tinker'),
      tooltip: clienttranslate('You can discard a Stealth token to draw a Tool. When any player finds a Tool, they may draw two Tool cards, keep one and discard the other.'),
    ),
    new CardInfo(
      name: 'rook1',
      title: clienttranslate('The Rook'),
      subhead: clienttranslate('Mastermind'),
      ability: clienttranslate('Orders'),
      choice_description: clienttranslate('a player token to move'),
      tooltip: clienttranslate('Once per turn you may spend an action to move another player one tile. Ignore move costs such as those from Deadbolt and Laser. Follow all other normal movement rules.'),
    ),
    new CardInfo(
      name: 'rook2',
      title: clienttranslate('The Rook'),
      subhead: clienttranslate('Mastermind'),
      ability: clienttranslate('Disguise'),
      choice_description: clienttranslate('a player token to trade places'),
      tooltip: clienttranslate('You may spend your first action to trade places with any player. This does not count as entering the tile for either of you.'),
    ),
    new CardInfo(
      name: 'spotter1',
      title: clienttranslate('The Spotter'),
      subhead: clienttranslate('Psychic Gone Rogue'),
      ability: clienttranslate('Clairvoyance'),
      choice_description: clienttranslate('to place card on top or bottom of deck'),
      tooltip: clienttranslate('Once per turn, you may spend one action to look at the top of the Patrol deck for your floor. Choose to place it on the top or bottom of the deck.'),
    ),
    new CardInfo(
      name: 'spotter2',
      title: clienttranslate('The Spotter'),
      subhead: clienttranslate('Psychic Gone Rogue'),
      ability: clienttranslate('Precognition'),
      choice_description: clienttranslate('to place card on top or bottom of deck'),
      tooltip: clienttranslate('Once per turn, you may spend one action to look at the top of the Event deck. Choose to place it on the top or bottom of the deck.'),
    ),
  ),
  CardType::TOOL => array(
    new CardInfo(
      name: 'blueprints',
      title: clienttranslate('Blueprints'),
      choice_description: clienttranslate('any tile to peek'),
      tooltip: clienttranslate('Discard to peek at any one tile on any floor.'),
    ),
    new CardInfo(
      name: 'crowbar',
      title: clienttranslate('Crowbar'),
      choice_description: clienttranslate('an adjacent tile to disable'),
      tooltip: clienttranslate('Discard to permanently disable an adjacent tile. It can no longer block movement or trigger alarms.'),
    ),
    new CardInfo(
      name: 'crystal-ball',
      title: clienttranslate('Crystal Ball'),
      choice_description: clienttranslate('to reorder the 3 upcoming events'),
      tooltip: clienttranslate('Discard to look at the top 3 events. Put them back in any order.'),
    ),
    new CardInfo(
      name: 'donuts',
      title: clienttranslate('Donuts'),
      choice_description: clienttranslate('any guard to lose all movement for one turn'),
      tooltip: clienttranslate('Place the donuts under any guard. Next time that guard would move, he instead loses all movement and the donuts are discarded.'),
    ),
    new CardInfo(
      name: 'dynamite',
      title: clienttranslate('Dynamite'),
      choice_description: clienttranslate('an adjacent wall to remove'),
      tooltip: clienttranslate('Discard to destroy a wall adjacent to you that players and guard may pass through. Trigger an alarm in the player\s current tile.'),
    ),
    new CardInfo(
      name: 'emp',
      title: clienttranslate('E.M.P.'),
      tooltip: clienttranslate('Remove all alarms from all floors. No alarms triggered on any floor until your next turn. Discard at the start of your next turn.'),
    ),
    new CardInfo(
      name: 'invisible-suit',
      title: clienttranslate('Invisible Suit'),
      tooltip: clienttranslate('Discard to not be seen by Guards or Cameras while moving and gain one additional action this turn.'),
    ),
    new CardInfo(
      name: 'makeup-kit',
      title: clienttranslate('Makeup Kit'),
      tooltip: clienttranslate('Discard to give all players on your current tile a Stealth token.'),
    ),
    new CardInfo(
      name: 'rollerskates',
      title: clienttranslate('Rollerskates'),
      tooltip: clienttranslate('Discard to gain two additional actions this turn.'),
    ),
    new CardInfo(
      name: 'smoke-bomb',
      title: clienttranslate('Smoke Bomb'),
      tooltip: clienttranslate('Discard to add three Stealth tokens to the current tile. These tokens may only be used in this room.'),
    ),
    new CardInfo(
      name: 'stethoscope',
      title: clienttranslate('Stethoscope'),
      choice_description: clienttranslate('the die you want to change (click on the die) or'),
      tooltip: clienttranslate('Discard after a cracking attempt to change the result of any one die to any side you wish.'),
    ),
    new CardInfo(
      name: 'thermal-bomb',
      title: clienttranslate('Thermal Bomb'),
      choice_description: clienttranslate('up or down to create stairs'),
      tooltip: clienttranslate('Discard to make stairs up or down from current tile. Mark with a stair token. Trigger alarm in the player current\'s tile.'),
    ),
    new CardInfo(
      name: 'virus',
      title: clienttranslate('Virus'),
      choice_description: clienttranslate('a computer to add hack tokens'),
      tooltip: clienttranslate('Discard to add three hack tokens to any computer room.'),
    ),
  ),
  CardType::LOOT => array(
    new CardInfo(
      name: 'bust',
      title: clienttranslate('Bust'),
      tooltip: clienttranslate('You may not use tools while holding the Bust.'),
    ),
    new CardInfo(
      name: 'chihuahua',
      title: clienttranslate('Chihuahua'),
      tooltip: clienttranslate('Each turn, roll a die. If 6, trigger an alarm on your tile.'),
    ),
    new CardInfo(
      name: 'cursed-goblet',
      title: clienttranslate('Cursed Goblet'),
      tooltip: clienttranslate('Player who draws the Cursed Goblet loses one Stealth.'),
    ),
    new CardInfo(
      name: 'gemstone',
      title: clienttranslate('Gemstone'),
      tooltip: clienttranslate('Pay an extra action to enter a tile occupied by another player.'),
    ),
    new CardInfo(
      name: 'gold-bar',
      title: clienttranslate('Gold Bar'),
      tooltip: clienttranslate('Find the other Gold Bar, put in play. Only one can be carried per player.'),
      nbr: 2,
    ),
    new CardInfo(
      name: 'isotope',
      title: clienttranslate('Isotope'),
      tooltip: clienttranslate('Trigger an alarm when entering a Thermo tile while holding the Isotope.'),
    ),
    new CardInfo(
      name: 'keycard',
      title: clienttranslate('Keycard'),
      tooltip: clienttranslate('Holder must be present to roll dice for cracking any safe.'),
    ),
    new CardInfo(
      name: 'mirror',
      title: clienttranslate('Mirror'),
      tooltip: clienttranslate('-1 action while holding the Mirror. Holder does not trigger Laser alarms.'),
    ),
    new CardInfo(
      name: 'painting',
      title: clienttranslate('Painting'),
      tooltip: clienttranslate('Holder may not travel through Secret Doors or Service Ducts.'),
    ),
    new CardInfo(
      name: 'persian-kitty',
      title: clienttranslate('Persian Kitty'),
      tooltip: clienttranslate('Each turn roll a die. If 1 or 2, Kitty moves 1 tile towards the nearest alarm. When "escaped", it doesn’t move. The owner of the loot must catch it before escape.'),
    ),
    new CardInfo(
      name: 'stamp',
      title: clienttranslate('Stamp'),
      tooltip: clienttranslate('When 3 actions or fewer are used by holder, trigger an event.'),
    ),
    new CardInfo(
      name: 'tiara',
      title: clienttranslate('Tiara'),
      tooltip: clienttranslate('Guards will see you from adjacent tiles while you are moving.'),
    ),
  ),
  CardType::EVENT => array(
    new CardInfo(
      name: 'brown-out',
      title: clienttranslate('Brown Out'),
      tooltip: clienttranslate('Alarm tokens on all floors are removed. Draw a new Patrol card for each alarm removed.'),
    ),
    new CardInfo(
      name: 'buddy-system',
      title: clienttranslate('Buddy System'),
      choice_description: clienttranslate('a player token to move to your tile'),
      tooltip: clienttranslate('Choose a player. Move their piece onto your current tile. Does not count as entering.'),
    ),
    new CardInfo(
      name: 'change-of-plans',
      title: clienttranslate('Change of plans'),
      tooltip: clienttranslate('Activate the next Patrol card on your floor.'),
    ),
    new CardInfo(
      name: 'crash',
      title: clienttranslate('Crash!'),
      tooltip: clienttranslate('Set Guard destination on your floor to your tile.'),
    ),
    new CardInfo(
      name: 'daydreaming',
      title: clienttranslate('Daydreaming'),
      tooltip: clienttranslate('The Guard on your floor has one less movement this turn.'),
    ),
    new CardInfo(
      name: 'dead-drop',
      title: clienttranslate('Dead drop'),
      tooltip: clienttranslate('Current player passes all tools and loot to the player on their right.'),
    ),
    new CardInfo(
      name: 'espresso',
      title: clienttranslate('Expresso'),
      tooltip: clienttranslate('The Guard on your floor has one additional movement this turn.'),
    ),
    new CardInfo(
      name: 'freight-elevator',
      title: clienttranslate('Freight elevator'),
      tooltip: clienttranslate('Fall up one floor. Does not count as entering the tile.'),
    ),
    new CardInfo(
      name: 'go-with-your-gut',
      title: clienttranslate('Go with your gut'),
      choice_description: clienttranslate('an adjacent unexplored tile to move to'),
      tooltip: clienttranslate('If you are adjacent to an explored tile, move into it now. Choose if there is more than one.'),
    ),
    new CardInfo(
      name: 'gymnastics',
      title: clienttranslate('Gymnastics'),
      tooltip: clienttranslate('Walkway tiles act as stairs for one round. Leave this in front of you and remove it at the start of your next turn.'),
    ),
    new CardInfo(
      name: 'heads-up',
      title: clienttranslate('Heads up!'),
      tooltip: clienttranslate('The next player gains an additional action on their turn.'),
    ),
    new CardInfo(
      name: 'jump-the-gun',
      title: clienttranslate('Jump the gun'),
      tooltip: clienttranslate('Skip the next player\'s turn (including Guard Movement).'),
    ),
    new CardInfo(
      name: 'jury-rig',
      title: clienttranslate('Jury-rig'),
      tooltip: clienttranslate('Draw a tool.'),
    ),
    new CardInfo(
      name: 'keycode-change',
      title: clienttranslate('Keycode change'),
      tooltip: clienttranslate('Any open keypad tiles are now locked again. Roll a 6 to enter and re-open.'),
    ),
    new CardInfo(
      name: 'lampshade',
      title: clienttranslate('Lampshade'),
      tooltip: clienttranslate('Gain a Stealth.'),
    ),
    new CardInfo(
      name: 'lost-grip',
      title: clienttranslate('Lost grip'),
      tooltip: clienttranslate('Fall one floor. This does not count as entering a tile.'),
    ),
    new CardInfo(
      name: 'peekhole',
      title: clienttranslate('Peekhole'),
      choice_description: clienttranslate('an adjacent tile (also through a wall or up/down floors) to peek'),
      tooltip: clienttranslate('You may peek at one adjacent tile, even through a wall or up/down floors. Resolve immediately.'),
    ),
    new CardInfo(
      name: 'reboot',
      title: clienttranslate('Reboot'),
      tooltip: clienttranslate('Set Hacks on any computer rooms to one token.'),
    ),
    new CardInfo(
      name: 'shift-change',
      title: clienttranslate('Shift change'),
      tooltip: clienttranslate('Guard does not move on your floor. Instead, Guards on the other floors move this turn (if revealed).'),
    ),
    new CardInfo(
      name: 'shoplifting',
      title: clienttranslate('Shoplifting'),
      tooltip: clienttranslate('Alarms are triggered on all laboratory tiles that have had tools taken from them.'),
    ),
    new CardInfo(
      name: 'squeak',
      title: clienttranslate('Squeak!'),
      tooltip: clienttranslate('Move the Guard on your floor one tile towards the nearest character.'),
    ),
    new CardInfo(
      name: 'switch-signs',
      title: clienttranslate('Switch signs'),
      tooltip: clienttranslate('The Guard on your floor and his destination swap positions.'),
    ),
    new CardInfo(
      name: 'throw-voice',
      title: clienttranslate('Throw voice'),
      choice_description: clienttranslate('an adjacent tile to move the guard destination to'),
      tooltip: clienttranslate('Move the Guard destination into an adjacent tile from its current location.'),
    ),
    new CardInfo(
      name: 'time-lock',
      title: clienttranslate('Time lock'),
      tooltip: clienttranslate('Players cannot move up or down through stairs for one round. Leave this in front of you and remove at the start of your next turn.'),
    ),
    new CardInfo(
      name: 'video-loop',
      title: clienttranslate('Video loop'),
      tooltip: clienttranslate('All camera tiles are disabled for one round. Leave this in front of you and remove at the start of your next turn.'),
    ),
    new CardInfo(
      name: 'where-is-he',
      title: clienttranslate('Where is he?'),
      tooltip: clienttranslate('Guard on your floor jumps to his current destination.'),
    ),
  ),
);

$this->patrol_types = array(
  CardType::patrol(1) => new DeckType('patrol1'),
  CardType::patrol(2) => new DeckType('patrol2'),
  CardType::patrol(3) => new DeckType('patrol3'),
);

// Patrol card faces are generated per board size: see patrolNames() / patrolInfo() in burglebros.game.php.

// Name to safe dice numbers
$this->tile_types = array(
  'atrium' => array(3, 4),
  'camera' => array(1, 2, 3, 6),
  'fingerprint-computer' => array(4),
  'laser-computer' => array(5),
  'motion-computer' => array(6),
  'deadbolt' => array(1, 2, 3),
  'detector' => array(4, 5, 6),
  'fingerprint' => array(4, 5, 6),
  'foyer' => array(1, 2),
  'keypad' => array(4, 5, 6),
  'laboratory' => array(3, 4),
  'laser' => array(1, 2, 3),
  'lavatory' => array(5),
  'motion' => array(1, 2, 3),
  'safe' => array(0, 0, 0),
  'secret-door' => array(1, 2),
  'service-duct' => array(5, 6),
  'stairs' => array(4, 5, 6),
  'thermo' => array(1, 2, 3),
  'walkway' => array(1, 2, 3),
);

// Playing the Office Job should use cards with white circles: Camera (3), Fingerprint (2), Laser (2), Motion (2), Thermo (2), Deadbolt (2), Keypad (2), Foyer (2), Secret Door (2), Service Duct (2), Laboratory (1), Lavatory (1), Walkway (2), Computer Room (3), and Stairs (2)
$this->tile_types_office_job = array(
  'atrium' => array(FALSE, FALSE),
  'camera' => array(FALSE, 2, 3, 6),
  'fingerprint-computer' => array(4),
  'laser-computer' => array(5),
  'motion-computer' => array(6),
  'deadbolt' => array(FALSE, 2, 3),
  'detector' => array(FALSE, FALSE, FALSE),
  'fingerprint' => array(4, 5, FALSE),
  'foyer' => array(1, 2),
  'keypad' => array(4, FALSE, 6),
  'laboratory' => array(3, FALSE),
  'laser' => array(1, 2, FALSE),
  'lavatory' => array(5),
  'motion' => array(1, FALSE, 3),
  'safe' => array(0, 0, FALSE),
  'secret-door' => array(1, 2),
  'service-duct' => array(5, 6),
  'stairs' => array(4, 5, FALSE),
  'thermo' => array(1, 2, FALSE),
  'walkway' => array(1, FALSE, 3),
);

$this->tile_distribution = array(
  'safe' => array('name' => clienttranslate('Safe'), 'nb' => 3),
  'stairs' => array('name' => clienttranslate('Stairs'), 'nb' => 3),
  'walkway' => array('name' => clienttranslate('Walkway'), 'nb' => 3),
  'laboratory' => array('name' => clienttranslate('Laboratory'), 'nb' => 2),
  'lavatory' => array('name' => clienttranslate('Lavatory'), 'nb' => 1),
  'service-duct' => array('name' => clienttranslate('Service Duct'), 'nb' => 2),
  'secret-door' => array('name' => clienttranslate('Secret Door'), 'nb' => 2),
  'fingerprint-computer' => array('name' => clienttranslate('Fingerprint Computer'), 'nb' => 1),
  'laser-computer' => array('name' => clienttranslate('Laser Computer'), 'nb' => 1),
  'motion-computer' => array('name' => clienttranslate('Motion Computer'), 'nb' => 1),
  'camera' => array('name' => clienttranslate('Camera'),'nb' => 4),
  'laser' => array('name' => clienttranslate('Laser'), 'nb' => 3),
  'motion' => array('name' => clienttranslate('Motion'), 'nb' => 3),
  'detector' => array('name' => clienttranslate('Detector'), 'nb' => 3),
  'fingerprint' => array('name' => clienttranslate('Fingerprint'), 'nb' => 3),
  'thermo' => array('name' => clienttranslate('Thermo'), 'nb' => 3),
  'keypad' => array('name' => clienttranslate('Keypad'), 'nb' => 3),
  'deadbolt' => array('name' => clienttranslate('Deadbolt'), 'nb' => 3),
  'foyer' => array('name' => clienttranslate('Foyer'), 'nb' => 2),
  'atrium' => array('name' => clienttranslate('Atrium'), 'nb' => 2),
);

// Playing the Office Job should use cards with white circles: Camera (3), Fingerprint (2), Laser (2), Motion (2), Thermo (2), Deadbolt (2), Keypad (2), Foyer (2), Secret Door (2), Service Duct (2), Laboratory (1), Lavatory (1), Walkway (2), Computer Room (3), and Stairs (2)
$this->tile_distribution_office_job = array(
  'safe' => array('name' => clienttranslate('Safe'), 'nb' => 2),
  'stairs' => array('name' => clienttranslate('Stairs'), 'nb' => 2),
  'walkway' => array('name' => clienttranslate('Walkway'), 'nb' => 2),
  'laboratory' => array('name' => clienttranslate('Laboratory'), 'nb' => 1),
  'lavatory' => array('name' => clienttranslate('Lavatory'), 'nb' => 1),
  'service-duct' => array('name' => clienttranslate('Service Duct'), 'nb' => 2),
  'secret-door' => array('name' => clienttranslate('Secret Door'), 'nb' => 2),
  'fingerprint-computer' => array('name' => clienttranslate('Fingerprint Computer'), 'nb' => 1),
  'laser-computer' => array('name' => clienttranslate('Laser Computer'), 'nb' => 1),
  'motion-computer' => array('name' => clienttranslate('Motion Computer'), 'nb' => 1),
  'camera' => array('name' => clienttranslate('Camera'),'nb' => 3),
  'laser' => array('name' => clienttranslate('Laser'), 'nb' => 2),
  'motion' => array('name' => clienttranslate('Motion'), 'nb' => 2),
  'fingerprint' => array('name' => clienttranslate('Fingerprint'), 'nb' => 2),
  'thermo' => array('name' => clienttranslate('Thermo'), 'nb' => 2),
  'keypad' => array('name' => clienttranslate('Keypad'), 'nb' => 2),
  'deadbolt' => array('name' => clienttranslate('Deadbolt'), 'nb' => 2),
  'foyer' => array('name' => clienttranslate('Foyer'), 'nb' => 2),
);

$this->token_types = array(
  array('name' => 'alarm', 'color' => '#CE5638'),
  array('name' => 'cat', 'color' => '#8E8644'),
  array('name' => 'safe', 'color' => '#74B189'),
  array('name' => 'crow', 'color' => '#C9C0BD'),
  array('name' => 'hack', 'color' => '#C6A7BE'),
  array('name' => 'open', 'color' => '#DDA860'),
  array('name' => 'stairs', 'color' => '#86939D'),
  array('name' => 'stealth', 'color' => '#568F9F'),
  array('name' => 'thermal', 'color' => '#74B189'),
  array('name' => 'diamond', 'color' => '#DDA860'),
  array('name' => 'crowbar', 'color' => '#74B189'),
  array('name' => 'keypad', 'color' => '#DDA860'),
);

$this->player_choices = array(
  PlayerChoice::NONE => 'none',
  PlayerChoice::TRADE => 'trade',
  PlayerChoice::ROOK1 => 'rook1',
  PlayerChoice::ROOK2 => 'rook2',
  PlayerChoice::SQUEAK => 'squeak',
);

$this->special_choices = array(
  SpecialChoice::NONE => 'none',
  SpecialChoice::ROOK1 => 'rook1',
  SpecialChoice::CLOSEST_ALARM => 'closest_alarm',
);

// Resume the right state after chooseAlarm
$this->state_after_alarms = array(
  State::PLAYER_TURN => 'playerTurn',
  State::MOVE_GUARD => 'moveGuard',
  State::END_ACTION => 'endAction',
);
