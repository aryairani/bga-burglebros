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
        // Check if the layout needs a shaft (black tile) - e.g. for Fort Knox      	
        $size = $this->getSquareSize();
        if ($size === 5) {
			$values[] = "('shaft',$index,'deck',0)";
			$values[] = "('shaft',". ++$index .",'deck',0)";
			$values[] = "('shaft',". ++$index .",'deck',0)";
        }
        shuffle($values);
        $sql = "INSERT INTO tile (card_type,card_type_arg,card_location,safe_die) VALUES ";
        self::DbQuery($sql.implode($values, ','));

        $this->setupTiles();
        $this->setupWalls();
	}

    function setupTiles() {
        $safes = $this->game->tiles->getCardsOfType('safe');
        $stairs = $this->game->tiles->getCardsOfType('stairs');
        $shafts = $this->game->tiles->getCardsOfType('shaft');
        $size = $this->getSquareSize();
        $size_sq = $size * $size - 1;
        $shaft_index = rand(0, $size_sq);
        $max_floor = $this->getFloorCount();

        // Grab a safe and stair for each floor, and move to the floor "deck"
        // Move all the stairs and safe to "remove" cards out of the deck even if only playing 2 floors
        for ($floor=1; $floor <= 3; $floor++) {
            $safe = array_shift($safes);
            $stair = array_shift($stairs);
            $card_ids = array($safe['id'], $stair['id']);
            if (count($shafts) > 0) {
            	$shaft = array_shift($shafts);
            	$card_ids[] = $shaft['id'];
            }
            $this->game->tiles->moveCards($card_ids, "floor$floor");
        }
        $this->game->tiles->shuffle('deck');
        // Grab tiles per floor "deck" and shuffle (14 for 4 cards square size || 22 for 5 cards square size)
        $square_size = $this->getSquareSize();
        $cards_to_draw = $square_size === 4 ? 14 : 22;
        for ($floor=1; $floor <= $max_floor; $floor++) {
            $this->game->tiles->pickCardsForLocation($cards_to_draw, 'deck', "floor$floor");
            $this->game->tiles->shuffle("floor$floor");
            // Switch cards from shaft position
            if (count($shafts) > 0) {
	            $card = array_values($this->game->tiles->getCardsInLocation("floor$floor", $shaft_index))[0];
	            $shaft = array_values($this->game->tiles->getCardsOfTypeInLocation("shaft", null, "floor$floor"))[0];
	            $this->game->tiles->moveCard($card['id'], "floor$floor", $shaft['location_arg']);
	            $this->game->tiles->moveCard($shaft['id'], "floor$floor", $card['location_arg']);
	        }
        }
    }

	function setupWalls() {
		// if ($this->game->getGameStateValue('randomWalls') == 1) {
		// 	$walls = $this->default_walls;
		// } else {
			$walls = $this->randomWalls();
		// }
		var_dump($walls);
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

	public function randomizeWalls() {
		// Dump walls db and recreate all the walls
		self::DbQuery("TRUNCATE wall");
		$this->setupWalls();
	}

	function randomWalls() {
		$size = $this->getSquareSize();
		$size_sq = $size * $size - 1;
		$dec = $size - 1;
		$expected_walls = $size === 4 ? 8 : 12;
		$max_walls = 2 * $size * ($size - 1);
		$max_floor = $this->getFloorCount();
		$shaft_walls = [];
		$random_walls = [];

		$security = 0;

		for ($floor=1; $floor <= $max_floor; $floor++) {
			$shaft = $this->getShaftPosition($floor);
			if ($shaft) {
				$shaft_walls = $this->getWalls($shaft);
				// foreach ($shaft_walls as $index) {
				// 	$permanent_walls[$index] = TRUE;
				// }
			}
			while (true) {
				// Reset walls results on each while loop
				$walls = $shaft_walls;
				for ($w=0; $w < $expected_walls; $w++) { 
					$n = rand(0, $max_walls);
					if (isset($walls[$n])) {
						$w--;
					} else {
						$walls[$n] = TRUE;
					}
				}
				if ($security++ > 3) {
					// var_dump($walls);
					var_dump("break in random walls");
					break;
				}
				if ($this->checkLayout($walls, $floor))
					break;
			}
			// Transform back the randomizer to the expected wall database format
			foreach ($walls as $w => $b) {
				$offset = $w % ($size + $dec);	# consider the offset on two consecutive 'rows' of walls
				$row = floor($w / ($size + $dec));
				if ($offset < $size) {
					$random_walls[$floor]['vertical'][] = (int) $row * $dec + $offset;
				} else {
					$random_walls[$floor]['horizontal'][] = (int) $row + ($offset - $dec) * $dec ;
				}
			}
			if ($shaft) {
				$random_walls[$floor]['shaft'] = $shaft;
			}
		}
		$this->game->dump('*** random_walls',$random_walls);
		return $random_walls;
	}

	function checkLayout($walls, $floor) {
		$shaft = $this->getShaftPosition($floor);
		$floor_tiles = $this->toFloor($walls);
		$size = $this->getSquareSize();
		$size_sq = $size * $size - 1;
		$visited = 0;
		// Consider shaft tile already visited
		if ($shaft) {
			$floor_tiles[$shaft]['v'] = TRUE;
			$visited++;
		}
		$check = [$shaft === 0 ? 1 : 0];
		$security = 0;
		// Try to visit every tile of the floor
		while (count($check) > 0) {
			$next = array_pop($check);
			$tile = &$floor_tiles[$next];		# update $floor_tiles variable
			if (isset($tile['v'])) continue;	# do not visit a tile twice
			$visited++;
			$tile['v'] = TRUE;
			if (isset($tile['n'])) $check[] = $next - $size;	#up
			if (isset($tile['e'])) $check[] = $next + 1;		#right
			if (isset($tile['s'])) $check[] = $next + $size;	#bottom
			if (isset($tile['w'])) $check[] = $next - 1;		#left
			if ($security++ > 300) {
				var_dump("break in checkLayout");
				break;
			}
		}
		$this->game->dump('*** visited', $visited);
		// Return true if visited all the squares of the floor
		return $visited === $size_sq;
	}

	function toFloor($walls) {
		// Create a floor (an array of [tile_index][n|e|s|w] => true if unblocked)
		$size = $this->getSquareSize();
		$floor = [];
		$i = 0;
		for ($x=0; $x < $size; $x++) { 
			for ($y=0; $y < $size; $y++) { 
				if (!isset($walls[$i])) {
					$floor[$y * $size + $x]['e'] = TRUE;
					$floor[$y * $size + $x + 1]['w'] = TRUE;
				}
				$i++;
			}
			if ($y < $size - 1) {
				for ($x=0; $x < $size; $x++) { 
					if (!$walls[$i]) {
						$floor[$y * $size + $x]['s'] = TRUE;
						$floor[($y + 1) * $size + $x]['n'] = TRUE;
					}
				}
				$i++;
			}
		}
		return $floor;
	}

	function getWalls($tile) {
		// Return each available wall positions for a tile
		$size = $this->getSquareSize();
		$dec = $size - 1;
		$max_tiles = 2 * size * dec;
		$offset = $tile % $size;	# column offset
		$row = floor($tile / $size) * ($size + $dec);
		$index = $row + $offset;
		$res = [];
		if ($offset > 0)
			$rest[] = $index - 1;		# wall on the left
		if ($offset < $dec)
			$res[] = $index - $size;	# wall on the right
		if ($index >= $size + $dec)
			$res[] = $index - $size;	# wall on top
		if ($index + $dec < $max)
			$res[] = $index + $dec;		# wall on bottom
		return $res;
	}

	function getShaftPosition($floor) {
		$shaft = $this->game->tiles->getCardsOfTypeInLocation('shaft', null, "floor$floor");
		if ($shaft) {
			return $shaft[0]['location_arg'];
		} else {
			return NULL;
		}
	}

	public function getSquareSize() {
		// Return the square size of the board (5 for Fort Knox scenario, 4 otherwise)
		if ($this->game->getGameStateValue('scenario') == 3) {
			return 5;
		} else {
			return 4;
		}
	}

	public function getFloorCount() {
		// Return the number of floors (3 for the Bank job, 2 otherwise)
		if ($this->game->getGameStateValue('scenario') == 1) {
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
