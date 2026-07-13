
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- burglebros implementation : © Brian Gregg baritonehands@gmail.com
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` INT unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

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

CREATE TABLE IF NOT EXISTS `token` (
  `card_id` INT unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(24) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `wall` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `floor` INT NOT NULL,
  `vertical` boolean NOT NULL,
  `position` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- Example 2: add a custom field to the standard "player" table
ALTER TABLE `player` ADD `player_stealth_tokens` INT NOT NULL DEFAULT 3;

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

CREATE TABLE IF NOT EXISTS `solo_characters` (
  `player_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_name` varchar(32) NOT NULL,
  `player_color` varchar(32) NOT NULL,
  `player_avatar` varchar(10) NOT NULL,
  `player_score` INT NOT NULL DEFAULT 0,
  `player_stealth_tokens` INT NOT NULL DEFAULT 3,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;