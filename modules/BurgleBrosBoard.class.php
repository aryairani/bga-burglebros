<?php

/*
 * BurgleBrosBoard: all utility functions to create boards, generate walls...
 */
class BurgleBrosBoard extends APP_GameClass
{
	public $game;
	public function __construct($game) {
		$this->game = $game;
	}

	public $default_walls = array(
		1 => array(
			'vertical' => array(0, 5, 9, 10),
			'horizontal' => array(1, 4, 6, 11)
		),
		2 => array(
			'vertical' => array(0, 1, 2, 9, 10, 11),
			'horizontal' => array(4, 7)
		),
		3 => array(
			'vertical' => array(3, 5, 6, 7, 11),
			'horizontal' => array(1, 6, 7)
		)
	);

	public $default_walls_size_5 = array(
		1 => array(
			'vertical' => array(2, 4, 7, 8, 18),
			'horizontal' => array(2, 3, 6, 8, 11, 15, 18),
			'shaft' => 12
		),
		2 => array(
			'vertical' => array(1, 2, 7, 8, 14, 16, 18),
			'horizontal' => array(0, 6, 13, 15, 18),
			'shaft' => 12
		)
	);

	public function setupNewGame($players, $options) {
		$index = 1;
        $values = array();
        foreach ( $this->game->tile_types as $type => $dice ) {
            foreach ($dice as $die) {
                $values [] = "('$type',$index,'deck',$die)";
                $index++;
            }
        }
        shuffle($values);
        $sql = "INSERT INTO tile (card_type,card_type_arg,card_location,safe_die) VALUES ";
        self::DbQuery($sql.implode($values, ','));

        $this->setupTiles();
	}

    function setupTiles() {
        $safes = $this->game->tiles->getCardsOfType('safe');
        $stairs = $this->game->tiles->getCardsOfType('stairs');
        $max_floor = $this->getFloorCount();

        // Grab a safe and stair for each floor, and move to the floor "deck"
        // Move all the stairs and safe to "remove" cards out of the deck even if only playing 2 floors
        for ($floor=1; $floor <= 3; $floor++) {
            $safe = array_shift($safes);
            $stair = array_shift($stairs);
            $card_ids = array($safe['id'], $stair['id']);
            $this->game->tiles->moveCards($card_ids, "floor$floor");
        }
        $this->setupWalls();
        $this->game->tiles->shuffle('deck');
        // Grab tiles per floor "deck" and shuffle (14 for 4 cards square size || 22 for 5 cards square size)
        $square_size = $this->getSquareSize();
        $cards_to_draw = $square_size == 4 ? 14 : 22;
        for ($floor=1; $floor <= $max_floor; $floor++) {
            $this->game->tiles->pickCardsForLocation($cards_to_draw, 'deck', "floor$floor");
            $this->game->tiles->shuffle("floor$floor");
        }
    }

	function setupWalls() {
		if ($this->game->getGameStateValue('randomWalls') == 1) {
			$walls = $this->default_walls;
		} else {
			$walls = $this->randomWalls();
		}
		$max_floor = $this->getFloorCount();
		for ($floor=1; $floor <= $max_floor; $floor++) {
			foreach ($walls[$floor] as $dir => $positions) {
				$sql = 'INSERT INTO wall (floor, vertical, position) VALUES ';
				$values = array();
				foreach ($positions as $position) {
					$vertical = $dir == 'vertical' ? 1 : 0;
					$values [] = "($floor,$vertical,$position)";
				}
				$sql .= implode($values, ',');
				self::DbQuery($sql);
			}
		}
	}

	function randomWalls() {

	}

	public function getSquareSize() {
		// Return the square size of the board (5 for Fort Knox scenario, 4 otherwise)
		if ( $this->game->getGameStateValue('scenario') == 3) {
			return 5;
		} else {
			return 4;
		}
	}

	public function getFloorCount() {
		// Return the number of floors (3 for the Bank job, 2 otherwise)
		if ( $this->game->getGameStateValue('scenario') == 1) {
			return 3;
		} else {
			return 2;
		}
	}

	/*
	* getObjective: factory function to create a objective by ID
	*/
	// public function getObjective($objectiveId) 
	// {
	// 	if (!isset(self::$objectiveClasses[$objectiveId])) {
	// 		throw new BgaVisibleSystemException("getPower: Unknown objective $objectiveId");
	// 	}
	// 	$className = "Objective".self::$objectiveClasses[$objectiveId];
	// 	return new $className($this->game);
	// }
}
