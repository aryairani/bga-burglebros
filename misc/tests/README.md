# Unit tests

Local tests for the pure-logic modules (no BGA framework or database needed).

## Setup (once)

```sh
cd misc/tests
curl -L https://phar.phpunit.de/phpunit.phar -o phpunit.phar
```

`phpunit.phar` is gitignored and excluded from SFTP sync — do not upload it to
BGA Studio (`misc/` has a 1 MB cap).

## Run

```sh
cd misc/tests
php phpunit.phar
```

## Layout

- `legacy/` — verbatim copies of code as it existed before extraction, used as
  reference implementations in differential tests. Never edit the copied bodies.
- `*Test.php` — the suites. Rule-cited tests reference printed page numbers of
  the 2nd ed. Mark III v2.05 rulebook.

## Wall layout validation

The default wall layouts are ASCII floor plans in
`modules/BurgleBrosWallLayouts.class.php`. `WallLayoutTest.php` validates them:
plans parse, wall counts match the rulebook, every room is reachable, and the
Fort Knox empty space is consistent across floors and fully enclosed. After
editing a plan, run the suite (above) before deploying, and update the
transcription pin in `testPlansParseToOriginalArrays` to the new expected
positions.
