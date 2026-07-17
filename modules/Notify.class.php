<?php

/*
 * Notify: typed senders for this game's notifications — one method per
 * NotifType, taking that notification's payload. The wire format (name,
 * log string, args array) stays exactly what burglebros.js subscribes to.
 */
final class Notify
{
    public function __construct(private readonly burglebros $game) {}

    private function all(NotifType $type, string $log, array $args = []): void {
        $this->game->bga->notify->all($type->value, $log, $args);
    }

    /** Plain log entry; 'message' is handled by the framework, no JS subscription. */
    function message(string $log, array $args = []): void {
        $this->all(NotifType::Message, $log, $args);
    }

    function activatePlayer(int $player_id): void {
        $this->all(NotifType::ActivatePlayer, '', ['player_id' => $player_id]);
    }

    function characterChosen(string $player_name, int $player_id, array $character, string $character_name): void {
        $this->all(NotifType::CharacterChosen, clienttranslate('${player_name} chooses to play ${character_name}'), [
            'i18n' => ['character_name'],
            'player_name' => $player_name,
            'player_id' => $player_id,
            'character' => $character,
            'character_name' => $character_name,
        ]);
    }

    function tokensPicked(bool $synchronous, array $tokens): void {
        $this->all($synchronous ? NotifType::TokensPickedSync : NotifType::TokensPicked, '', [
            'tokens' => $tokens,
        ]);
    }

    function catEscaped(string $player_name): void {
        $this->all(NotifType::CatEscaped, clienttranslate('${player_name} must pick up the cat token before escaping'), [
            'player_name' => $player_name,
        ]);
    }

    function catPicked(string $player_name): void {
        $this->all(NotifType::CatPicked, clienttranslate('${player_name} picks up the cat token'), [
            'player_name' => $player_name,
        ]);
    }

    /** Sent as decrementStealth when a stealth is lost (the client animates the token), plain message when gained. */
    function stealthChanged(bool $lost, string $player_name, ?string $meeple_id): void {
        $this->all($lost ? NotifType::DecrementStealth : NotifType::Message, clienttranslate('${player_name} ${action} one stealth'), [
            'i18n' => ['action'],
            'action' => $lost ? clienttranslate('loses') : clienttranslate('gains'),
            'player_name' => $player_name,
            'meeple_id' => $meeple_id,
        ]);
    }

    function tileFlipped(array $tile, int $floor, int $undo_allowed, ?array $flipped_tiles = null): void {
        $args = [
            'tile' => $tile,
            'floor' => $floor,
            'undo_allowed' => $undo_allowed,
        ];
        if ($flipped_tiles !== null) {
            $args['flipped_tiles'] = $flipped_tiles;
        }
        $this->all(NotifType::TileFlipped, '', $args);
    }

    function nextPatrol(int $floor, array $discard_cards, array $top, int $deck_count): void {
        $this->all(NotifType::NextPatrol, '', [
            'floor' => $floor,
            'cards' => $discard_cards,
            'top' => $top,
            'deck_count' => $deck_count,
        ]);
    }

    function createGuardPath(int $floor, array $path): void {
        $this->all(NotifType::CreateGuardPath, '', ['floor' => $floor, 'path' => $path]);
    }

    function updateGuardPath(int $floor, array $path, int $position): void {
        $this->all(NotifType::UpdateGuardPath, '', [
            'floor' => $floor,
            'path' => $path,
            'position' => $position,
        ]);
    }

    function playerHand(int $player_id, array $hand, array $discard_ids): void {
        $this->all(NotifType::PlayerHand, '', [
            'player_id' => $player_id,
            'hand' => $hand,
            'discard_ids' => $discard_ids,
        ]);
    }

    function addTooltipToLog(string $player_name, array $card, string $title, string $tooltip): void {
        $this->all(NotifType::AddTooltipToLog, clienttranslate('${player_name} draws ${title} (${tooltip})'), [
            'i18n' => ['title', 'tooltip'],
            'card_id' => $card['id'],
            'card' => $card,
            'player_name' => $player_name,
            'title' => $title,
            'tooltip' => $tooltip,
        ]);
    }

    function eventCard(array $card, string $title, string $tooltip): void {
        $this->all(NotifType::EventCard, clienttranslate('Event Card: ${title} (${tooltip})'), [
            'card_id' => $card['id'],
            'card' => $card,
            'title' => $title,
            'tooltip' => $tooltip,
        ]);
    }

    function safeDieIncreased(int $die_num, array $token, int $floor): void {
        $this->all(NotifType::SafeDieIncreased, '', [
            'die_num' => $die_num,
            'token' => $token,
            'floor' => $floor,
        ]);
    }

    /** @param list<int> $roll_list individual die results */
    function diceRolled(string $player_name, array $roll_list, string $for): void {
        $this->all(NotifType::DiceRolled, clienttranslate('${player_name} rolled ${roll} for ${for}'), [
            'i18n' => ['for'],
            'player_name' => $player_name,
            'roll' => implode(',', $roll_list),
            'rolls' => $roll_list,
            'for' => $for,
        ]);
    }

    function patrolDieIncreased(int $die_num, array $token, int $floor): void {
        $this->all(NotifType::PatrolDieIncreased, '', [
            'die_num' => $die_num,
            'token' => $token,
            'floor' => $floor,
        ]);
    }

    function tileCards(int $tile_id, array $tokens): void {
        $this->all(NotifType::TileCards, '', ['tile_id' => $tile_id, 'tokens' => $tokens]);
    }

    function showFloor(int $floor, bool $delay): void {
        $this->all(NotifType::ShowFloor, '', ['floor' => $floor, 'delay' => $delay]);
    }

    /** @param int|string $floor a floor number, or 'all' */
    function updateWalls(string $log, int|string $floor, array $walls, array $tiles): void {
        $this->all(NotifType::UpdateWalls, $log, [
            'floor' => $floor,
            'walls' => $walls,
            'tiles' => $tiles,
        ]);
    }

    function removeWall(int $wall_id): void {
        $this->all(NotifType::RemoveWall, '', ['wall_id' => $wall_id]);
    }

    function playerEscape(int $player_id, string $player_name, int $token_id): void {
        $this->all(NotifType::PlayerEscape, clienttranslate('${player_name} escapes to the roof'), [
            'player_id' => $player_id,
            'player_name' => $player_name,
            'token_id' => $token_id,
        ]);
    }
}
