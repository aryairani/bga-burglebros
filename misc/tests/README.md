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
