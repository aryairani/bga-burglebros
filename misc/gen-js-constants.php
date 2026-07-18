<?php

/**
 * Dev-time code generator: exports the PHP enums that the JS client shares
 * vocabulary with into modules/burglebros.constants.js (a committed file,
 * loaded by burglebros.js as a define() dependency).
 *
 * Run from anywhere:  php misc/gen-js-constants.php
 * Then commit the regenerated modules/burglebros.constants.js.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once "$root/modules/NotifType.class.php";
require_once "$root/modules/CardType.class.php";
require_once "$root/modules/TileType.class.php";
require_once "$root/modules/TokenType.class.php";
require_once "$root/modules/DeckType.class.php";

/** @var array<string, class-string> JS object key => PHP enum */
$exports = [
    'NOTIF' => NotifType::class,
    'CARD_TYPE' => CardType::class,
    'TILE_TYPE' => TileType::class,
    'TOKEN_TYPE' => TokenType::class,
    'DECK_TYPE' => DeckType::class,
];

$out = <<<'JS'
// GENERATED FILE - do not edit. Regenerate with:  php misc/gen-js-constants.php
// Source of truth: the PHP enums in modules/ (NotifType, CardType, TileType, TokenType, DeckType).
// The values are load-bearing on the client (CSS classes, img/ basenames, gamedatas keys,
// notification wire names) and in the DB of running games - never rename them here or in PHP.
globalThis.BBCONST = Object.freeze({

JS;

foreach ($exports as $key => $enum) {
    $out .= "    // $enum (modules/" . basename((new ReflectionEnum($enum))->getFileName()) . ")\n";
    $out .= "    $key: Object.freeze({\n";
    foreach ($enum::cases() as $case) {
        $out .= "        $case->name: " . json_encode($case->value) . ",\n";
    }
    $out .= "    }),\n";
}
$out .= "});\n";

$target = "$root/modules/burglebros.constants.js";
$changed = !file_exists($target) || file_get_contents($target) !== $out;
file_put_contents($target, $out);
echo ($changed ? "wrote" : "unchanged") . ": $target\n";

// TypeScript twin for the (not yet deployed) src/ts build. Same values,
// exported as a module with literal types instead of a frozen global.
$ts = <<<'TS'
// GENERATED FILE - do not edit. Regenerate with:  php misc/gen-js-constants.php
// TypeScript twin of modules/burglebros.constants.js - same enums, same warnings:
// the values are load-bearing on the client and in the DB of running games,
// never rename them here or in PHP.
export const BBCONST = {

TS;

foreach ($exports as $key => $enum) {
    $ts .= "    // $enum (modules/" . basename((new ReflectionEnum($enum))->getFileName()) . ")\n";
    $ts .= "    $key: {\n";
    foreach ($enum::cases() as $case) {
        $ts .= "        $case->name: " . json_encode($case->value) . ",\n";
    }
    $ts .= "    },\n";
}
$ts .= "} as const;\n";

$tsTarget = "$root/src/ts/constants.ts";
$tsChanged = !file_exists($tsTarget) || file_get_contents($tsTarget) !== $ts;
file_put_contents($tsTarget, $ts);
echo ($tsChanged ? "wrote" : "unchanged") . ": $tsTarget\n";
