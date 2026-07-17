<?php

/*
 * TokenType: card_type values in the `token` table. The client uses the same
 * strings for CSS classes; display colors live in $token_types in material.inc.php.
 */
enum TokenType: string
{
    case Guard = 'guard';
    case Patrol = 'patrol';
    case Player = 'player';
    case Crack = 'crack';
    case Hack = 'hack';
    case Safe = 'safe';
    case Stealth = 'stealth';
    case Alarm = 'alarm';
    case Open = 'open';
    case Keypad = 'keypad';
    case Stairs = 'stairs';
    case Thermal = 'thermal';
    case Crowbar = 'crowbar';
    case Crow = 'crow';
    case Cat = 'cat';
    case Die = 'die'; // stored die values while a stethoscope roll is pending

    static function of(array $token): self {
        return self::from($token['type']);
    }
}
