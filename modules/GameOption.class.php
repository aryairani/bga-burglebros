<?php

/*
 * GameOption: ids of the table options declared in gameoptions.json
 * (gameoptions.json is JSON, so the ids there remain literal 100-106).
 */
final class GameOption
{
    const CHARACTER_ASSIGNMENT = 100;
    const LEVEL = 101;
    const SCENARIO = 102;
    const WALLS = 103;
    const SOLO_MULTI_CHARACTERS = 104;
    const DEADBOLT_DISTRIBUTION = 106;
}

// Values of each option, mirroring the value ids in gameoptions.json.
// Scenario (option 102) values live in Scenario.class.php;
// Solo multi-characters (option 104) values are plain character counts.

final class CharacterAssignment
{
    const RANDOM = 1;
    const RANDOM_ADVANCED = 2;
    const CHOICE = 3;
    const CHOICE_ADVANCED = 4;
}

final class Level
{
    const EASY = 1;
    const NORMAL = 2;
    const HARD = 3;
}

final class Walls
{
    const DEFAULT = 1;
    const RANDOM = 2;
}

final class DeadboltDistribution
{
    const FULLY_RANDOM = 1;
    const ONE_PER_FLOOR = 2;
}
