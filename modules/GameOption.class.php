<?php

/*
 * GameOption: ids of the table options declared in gameoptions.json
 * (gameoptions.json is JSON, so the ids there remain literal 100-106).
 */
enum GameOption: int
{
    case CharacterAssignment = 100;
    case Level = 101;
    case Scenario = 102;
    case Walls = 103;
    case SoloMultiCharacters = 104;
    case DeadboltDistribution = 106;
}

// Values of each option, mirroring the value ids in gameoptions.json.
// Scenario (option 102) values live in Scenario.class.php;
// Solo multi-characters (option 104) values are plain character counts.

enum CharacterAssignment: int
{
    case Random = 1;
    case RandomAdvanced = 2;
    case Choice = 3;
    case ChoiceAdvanced = 4;
}

enum Level: int
{
    case Easy = 1;
    case Normal = 2;
    case Hard = 3;
}

enum Walls: int
{
    case Default = 1;
    case Random = 2;
}

enum DeadboltDistribution: int
{
    case FullyRandom = 1;
    case OnePerFloor = 2;
}
