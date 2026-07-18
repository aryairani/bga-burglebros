<?php

/*
 * TileType: card_type values in the `tile` table (keys of $tile_types /
 * $tile_distribution in BurgleBrosMaterial). The client uses the same strings
 * for CSS classes and image filenames.
 */
enum TileType: string
{
    case Atrium = 'atrium';
    case Camera = 'camera';
    case FingerprintComputer = 'fingerprint-computer';
    case LaserComputer = 'laser-computer';
    case MotionComputer = 'motion-computer';
    case Deadbolt = 'deadbolt';
    case Detector = 'detector';
    case Fingerprint = 'fingerprint';
    case Foyer = 'foyer';
    case Keypad = 'keypad';
    case Laboratory = 'laboratory';
    case Laser = 'laser';
    case Lavatory = 'lavatory';
    case Motion = 'motion';
    case Safe = 'safe';
    case SecretDoor = 'secret-door';
    case ServiceDuct = 'service-duct';
    case Stairs = 'stairs';
    case Thermo = 'thermo';
    case Walkway = 'walkway';

    // Only in the 5x5 Fort Knox layout; not in the material tile lists
    case Shaft = 'shaft';

    // Pseudo-type: how a face-down tile is masked before being sent to the client
    case Back = 'back';

    static function of(array $tile): self {
        return self::from($tile['type']);
    }

    /** The computer tile that neutralizes this alarm tile when hacked (fingerprint/laser/motion only). */
    function computer(): self {
        return self::from($this->value . '-computer');
    }

    function isComputer(): bool {
        return str_ends_with($this->value, '-computer');
    }
}
