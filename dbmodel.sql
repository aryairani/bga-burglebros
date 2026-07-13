
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- burglebros implementation : © Brian Gregg baritonehands@gmail.com
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- The database schema is created from this file when the game starts. If you modify this file,
-- you have to restart a game to see your changes in database.
-- The standard tables ("global", "stats", "gamelog" and "player") already exist and must not be
-- created here. Scalar rules state (actionsRemaining, per-floor guard die values, etc.) lives in
-- "global" via the labels registered in burglebros.game.php.
-- Rulebook page references below are printed page numbers in BBRuleBook[MarkIII-v2.05].pdf.

-- All decks of cards, managed by one BGA Deck component ($this->cards).
--   card_type: 0=characters, 1=tools, 2=loot, 3=events (material.inc.php $card_types/$card_info);
--              4/5/6 = Patrol deck for floor 1/2/3 (printed p.3 "Ready the Guards").
--   card_type_arg: 1-based index into $card_info[type]; for patrol cards, into $patrol_names
--                  (the guard destination A1..D4, or A1..E5 in Fort Knox).
--   card_location: '<name>_deck' / '<name>_discard' (e.g. tools_deck); 'hand' (arg = player id)
--                  for held characters/tools/loot; 'tile' (arg = tile card_id) for cards sitting
--                  on the board (dropped loot, donuts); '<name>_oop' for cards removed from play
--                  (solo-mode removals, shaft-column patrol cards).
CREATE TABLE IF NOT EXISTS `card` (
  `card_id` INT unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- One row per room tile (Deck schema + 2 custom columns), managed by $this->tiles.
--   card_type: room name ('safe', 'stairs', 'camera', ...; 'shaft' = Fort Knox empty space).
--   card_location: 'floorN'; card_location_arg: grid position 0..15 (0..24 in Fort Knox's 5x5).
--   flipped: permanently revealed by Peek/Move (p.5). getTiles() masks unflipped tiles as
--            type 'back' toward clients, so hidden info never leaves the server.
--   safe_die: on normal tiles, the combination number printed in the tile's corner — roll it
--             to crack the safe in its row/column (p.6; per-type values in material.inc.php
--             $tile_types); on safe tiles (corner value 0), reused as the count of dice added
--             to that safe, 0-6 (p.6 "Add a Die to a Safe").
CREATE TABLE IF NOT EXISTS `tile` (
  `card_id` INT unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(24) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` INT NOT NULL DEFAULT 0,
  `flipped` boolean NOT NULL DEFAULT 0,
  `safe_die` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Every cardboard token (Deck schema), managed by $this->tokens; location 'deck' = supply,
-- 'tile' (arg = tile card_id) = on the board. Types (component list p.2):
--   guard, patrol (type_arg = floor): guard piece and his destination die marker (p.4)
--   crack (type_arg = floor): marks a safe once dice are added; die count is tile.safe_die
--   safe: green Cracked tokens covering rolled combination numbers (pp.6-7)
--   hack: on computer tiles, max 6 per computer (p.6)
--   alarm: alarm markers, one per tile max (p.9)
--   open: opened safes/keypads; keypad: keypad roll attempts
--   stealth: in location 'player' (arg = player id), mirrors player.player_stealth_tokens;
--            on tiles for Smoke Bomb room-bound stealth
--   stairs: the entrance Downstairs token (p.4) and Thermal Bomb stairs; thermal: bomb markers
--   crow / cat / crowbar: Raven's crow, Persian Kitty, disabled-tile markers
--   player (type_arg = player id): character piece; location 'tile', or 'roof' once escaped
CREATE TABLE IF NOT EXISTS `token` (
  `card_id` INT unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(24) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Wall segments between tiles: 8 per floor in the base game (p.1), 12 in Fort Knox (p.12).
--   vertical: orientation; position: 0-based index into that floor's vertical/horizontal
--   wall slots (layout math in BurgleBrosBoard).
CREATE TABLE IF NOT EXISTS `wall` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `floor` INT NOT NULL,
  `vertical` boolean NOT NULL,
  `position` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Authoritative stealth count per player (rules give 3, p.3; the difficulty option sets 6/1/3).
-- The stealth rows in `token` are kept in sync for display.
ALTER TABLE `player` ADD `player_stealth_tokens` INT NOT NULL DEFAULT 3;

-- Pending loot/tool trade offers between players on the same tile, and the cards each side
-- offers. UI staging for the trade flow, not a rulebook object.
CREATE TABLE IF NOT EXISTS `trade` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `current_player` INT unsigned NOT NULL,
  `other_player` INT unsigned NOT NULL,
  `deleted` boolean NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `trade_cards` (
  `trade_id` INT unsigned NOT NULL,
  `player_id` INT unsigned NOT NULL,
  `card_id` INT unsigned NOT NULL,
  PRIMARY KEY (`trade_id`, `card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- Shadow player table so one human can run multiple characters in solo mode
-- (soloMultiCharacters option); mirrors the columns the game reads from `player`.
CREATE TABLE IF NOT EXISTS `solo_characters` (
  `player_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_name` varchar(32) NOT NULL,
  `player_color` varchar(32) NOT NULL,
  `player_avatar` varchar(10) NOT NULL,
  `player_score` INT NOT NULL DEFAULT 0,
  `player_stealth_tokens` INT NOT NULL DEFAULT 3,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;
