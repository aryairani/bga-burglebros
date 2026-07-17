<?php

/*
 * GameStateValue: the game state values (framework globals) this game
 * registers in initGameStateLabels — label string plus numeric id.
 */
enum GameStateValue: string
{
    case ActionsRemaining = 'actionsRemaining';
    case EntranceTile = 'entranceTile';
    case MotionTileEntered = 'motionTileEntered';
    case PatrolDieCount1 = 'patrolDieCount1';
    case PatrolDieCount2 = 'patrolDieCount2';
    case PatrolDieCount3 = 'patrolDieCount3';
    case LaboratoryTileEntered = 'laboratoryTileEntered';
    case InvisibleSuitActive = 'invisibleSuitActive';
    case EmpPlayer = 'empPlayer';
    case CardChoice = 'cardChoice';
    case CharacterAbilityUsed = 'characterAbilityUsed';
    case AcrobatEnteredGuardTile = 'acrobatEnteredGuardTile';
    case TileChoice = 'tileChoice';
    case MotionTileExitChoice = 'motionTileExitChoice';
    case PlayerChoice = 'playerChoice';
    case PlayerChoiceArg = 'playerChoiceArg';
    case SpecialChoice = 'specialChoice';
    case SpecialChoiceArg = 'specialChoiceArg';
    case FirstAction = 'firstAction';
    case DrawToolsPlayer = 'drawToolsPlayer';
    case DrawToolsNextPlayer = 'drawToolsNextPlayer';
    case StealthDepleted = 'stealthDepleted';
    case PlayerPass = 'playerPass';
    case DropLoot = 'dropLoot';
    case UndoAllowed = 'undoAllowed';
    case RookDestinationTile = 'rookDestinationTile';
    case CurrentPlayer = 'currentPlayer';
    case RemainingMoves = 'remainingMoves';
    case StateAfterAlarm = 'stateAfterAlarm';
    case MoveDecreaseAfterAlarm = 'moveDecreaseAfterAlarm';
    case DonutsDropped = 'donutsDropped';

    // Table options: reading these returns the option value (ids from GameOption)
    case CharacterAssignment = 'characterAssignment';
    case Level = 'level';
    case Scenario = 'scenario';
    case RandomWalls = 'randomWalls';
    case SoloMultiCharacters = 'soloMultiCharacters';
    case DeadboltDistribution = 'deadboltDistribution';

    function id(): int {
        return match ($this) {
            self::ActionsRemaining => 10,
            self::EntranceTile => 11,
            self::MotionTileEntered => 15,
            self::PatrolDieCount1 => 16,
            self::PatrolDieCount2 => 17,
            self::PatrolDieCount3 => 18,
            self::LaboratoryTileEntered => 19,
            self::InvisibleSuitActive => 20,
            self::EmpPlayer => 21,
            self::CardChoice => 22,
            self::CharacterAbilityUsed => 23,
            self::AcrobatEnteredGuardTile => 24,
            self::TileChoice => 25,
            self::MotionTileExitChoice => 26,
            self::PlayerChoice => 27,
            self::PlayerChoiceArg => 36, // BUG (pre-existing): aliases UndoAllowed's id
            self::SpecialChoice => 28,
            self::SpecialChoiceArg => 29,
            self::FirstAction => 30,
            self::DrawToolsPlayer => 31,
            self::DrawToolsNextPlayer => 32,
            self::StealthDepleted => 33,
            self::PlayerPass => 34,
            self::DropLoot => 35,
            self::UndoAllowed => 36,
            self::RookDestinationTile => 37,
            self::CurrentPlayer => 38,
            self::RemainingMoves => 39,
            self::StateAfterAlarm => 40,
            self::MoveDecreaseAfterAlarm => 41,
            self::DonutsDropped => 42,

            self::CharacterAssignment => GameOption::CharacterAssignment->value,
            self::Level => GameOption::Level->value,
            self::Scenario => GameOption::Scenario->value,
            self::RandomWalls => GameOption::Walls->value,
            self::SoloMultiCharacters => GameOption::SoloMultiCharacters->value,
            self::DeadboltDistribution => GameOption::DeadboltDistribution->value,
        };
    }

    /** @return array<string, int> label => id, the shape initGameStateLabels expects */
    static function labels(): array {
        $labels = array();
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->id();
        }
        return $labels;
    }

    static function patrolDieCount(int $floor): self {
        return self::from("patrolDieCount$floor");
    }
}
