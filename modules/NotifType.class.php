<?php

/**
 * Notification types sent with $this->bga->notify->all().
 *
 * The backing values are the wire names the JS client subscribes to in
 * setupNotifications (burglebros.js), so they must stay byte-identical.
 * misc/gen-js-constants.php exports them to modules/burglebros.constants.js.
 */
enum NotifType: string
{
    /** Framework-builtin log-only notification (no game-specific JS handler). */
    case Message = 'message';

    case ActivatePlayer = 'activatePlayer';
    case AddTooltipToLog = 'addTooltipToLog';
    case CatEscaped = 'catEscaped';
    case CatPicked = 'catPicked';
    case CharacterChosen = 'characterChosen';
    case CreateGuardPath = 'createGuardPath';
    case DecrementStealth = 'decrementStealth';
    case DiceRolled = 'diceRolled';
    case EventCard = 'eventCard';
    case NextPatrol = 'nextPatrol';
    case PatrolDieIncreased = 'patrolDieIncreased';
    case PlayerEscape = 'playerEscape';
    case PlayerHand = 'playerHand';
    case RemoveWall = 'removeWall';
    case SafeDieIncreased = 'safeDieIncreased';
    case ShowFloor = 'showFloor';
    case TileCards = 'tileCards';
    case TileFlipped = 'tileFlipped';
    case TokensPicked = 'tokensPicked';
    /** Same client handler as TokensPicked, but queued synchronously (see setupNotifications). */
    case TokensPickedSync = 'tokensPickedSync';
    case UpdateGuardPath = 'updateGuardPath';
    case UpdateWalls = 'updateWalls';
}
