# Contributing to Laravel Translations

Thanks for helping! Bug reports, docs fixes and features are all welcome.

## Before you start

- **Bugs:** [open an issue](https://github.com/Ruvelo/laravel-translations/issues/new/choose) with the steps to reproduce, or go straight to a pull request with a failing test. For export problems, include the lang file (or a cut-down copy) that comes out wrong.
- **Features:** open an issue first if it's more than a small change, so we can agree on the shape before you spend time on it.
- **Security issues:** don't open an issue. See [SECURITY.md](https://github.com/Ruvelo/.github/blob/main/SECURITY.md).

## Setup

You need PHP 8.3+ and Composer. No database server: tests run on in-memory SQLite, and every test gets its own copy of the fixture lang folder.

```bash
git clone https://github.com/<you>/laravel-translations && cd laravel-translations
composer install
composer check
```

`composer check` runs exactly what CI runs:

| Command | What it does |
|---|---|
| `composer test` | PHPUnit, through Orchestra Testbench |
| `composer lint` | Code style check (Laravel Pint) |
| `composer format` | Fix code style |
| `composer analyse` | Static analysis (PHPStan level 8 with Larastan) |
| `composer demo` | Build the static demo into `build/` |

## Making a change

1. Branch from `main`.
2. Write a test that fails without your change. Feature tests live in `tests/Feature`, unit tests in `tests/Unit`, fixture lang files in `tests/Fixtures/lang`.
3. Keep the public API stable: `Ruvelo\Translations\Translations`, `Key`, `Entry`, the models, events, exceptions, config keys, routes and the JSON shapes. If you must change one, say so in the PR.
4. The package depends on `laravel/framework` only. Optional integrations go in `suggest` and behind `interface_exists()` checks.
5. Run `composer format` and `composer check`.
6. Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md).
7. Open the pull request. Screenshots help for anything visual.

## Where things live

```
src/Translations.php           The public PHP API
src/Models/Override.php        Override::put() and revert(): the one write path
src/Loader/                    The loader that lays overrides over Laravel's, and the key-recording translator
src/Support/Catalogue.php      Reads the lang files and overrides, builds entries and progress
src/Support/Placeholders.php   Placeholder checks (mirrored in resources/views/partials/check.blade.php)
src/Export/                    Writes lang files in place (PhpArrayFile, JsonFile)
src/Scanner/                   translations:scan
src/Import/                    translations:import
src/Http/                      The editor, the in-context endpoints, and Api/ for the JSON API
resources/views/               Blade views; the styles live in partials/styles.blade.php
resources/boost/               Laravel Boost guidelines
demo/                          Halyard's lang files, the demo build script and screenshots
```

## Style

- `declare(strict_types=1)` everywhere, typed properties and return types.
- No `@phpstan-ignore` or baseline entries: fix the cause.
- Comments explain *why*, not *what*.
- UI follows the [Ruvelo house style](https://github.com/Ruvelo/.github/blob/main/BRAND.md): CSS variables, no build step, works without JavaScript, light and dark.

By contributing you agree that your work is released under the [MIT license](LICENSE) and that you'll follow the [code of conduct](https://github.com/Ruvelo/.github/blob/main/CODE_OF_CONDUCT.md).
