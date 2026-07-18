// Starter domain types for the TS port (Phase 0 of the front-end plan).
// Flesh these out against captured gamedatas fixtures during the port.
// Note: the classic BGA DB layer delivers most numeric fields as strings.

/** A row from a BGA Deck-backed table (cards and tokens share this shape). */
interface BurgleBrosCard {
    id: string;
    type: string;
    type_arg: string;
    location: string;
    location_arg: string;
}

interface BurgleBrosTile extends BurgleBrosCard {
    safe_die: string;
}

interface BurgleBrosToken extends BurgleBrosCard {
    letter?: string;
    die_num?: number;
    floor?: number;
}

interface BurgleBrosWall {
    id: string;
    floor: string;
    position: string;
    vertical: string;
}

interface BurgleBrosPlayer extends Player {
    hand: { [cardId: string]: BurgleBrosCard };
    character: BurgleBrosCard;
    escaped: string;
    player_name: string;
    player_color: string;
}

interface BurgleBrosGamedatas extends Gamedatas<BurgleBrosPlayer> {
    floor_count: number;
    square_size: number;
    shaft_position: number;
    solo_characters: number;
    active_player_id: number | string;
    undo_allowed: number;
    walls: BurgleBrosWall[];
    flipped_tiles: { [tileId: string]: BurgleBrosTile };
    player_tokens: { [tokenId: string]: BurgleBrosToken };
    guard_tokens: { [tokenId: string]: BurgleBrosToken };
    patrol_tokens: { [tokenId: string]: BurgleBrosToken };
    crack_tokens: { [tokenId: string]: BurgleBrosToken };
    generic_tokens: { [tokenId: string]: BurgleBrosToken };
    card_tokens: { [tileId: string]: { type: string; count: number } };
    patrol_counters: { [floor: number]: number };
    guard_paths: { [floorKey: string]: number[] | null };
    // Plus dynamic keys: floorN, patrolN_discard, patrolN_discard_top,
    // card_types, card_info, patrol_types, patrol_names, token_types,
    // tile_distribution... typed as they get used by the port.
    [key: string]: any;
}
