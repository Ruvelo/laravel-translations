# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-10-08

### Added

- A new languages dashboard: an overall progress ring with the key numbers, a ring, status and a segmented translated / not exported / missing bar per language, a "Translate N missing" shortcut, and a recent-edits feed that flags placeholder problems.
- `Translations::editorNames()` for the display names behind a list of edits.
- Round language flags beside each language, from the bundled [circle-flags](https://github.com/HatScripts/circle-flags) set (MIT). A locale's region wins (`pt_BR` → Brazil), then its language; `translations.flags` maps a locale to another flag or turns them off.

## [1.0.0] - 2026-10-08

First release.

### Added

- Overrides edited in the browser, laid over Laravel's own loader: live at once, without writing to the server's files. PHP files (nested keys, sub-folders, `lang/vendor` packages) and JSON files.
- Compiled overrides cached per language and file, cleared when one changes.
- `translations:export` writes pending edits into the lang files in place, keeping comments, quotes, alignment and key order; `--dry-run` shows the diff, `--prune` drops the exported overrides. `translations:prune` for after a deploy.
- Language overview with percent translated, missing and not-exported counts; add a language from the browser.
- Editor table with source and translation side by side, save on blur, Enter to the next string, Esc to undo, and filters for missing, not exported and needs a look, search, and per-file view. Plain forms without JavaScript.
- Placeholder checks for `:name`, `{count}`, plural forms, `{0}` / `[1,*]` ranges and HTML tags, in PHP and as you type.
- Pending changes review with the file's text, the site's text, the editor, a revert per change, and a flag when the file changed after the edit.
- In-context editing: an "Edit translations" toolbar for editors, injected into pages or placed with `<x-translations::toolbar />`, listing the strings the page used. A `translations:saved` DOM event for apps that re-render without a reload.
- `translations:scan` finds keys used in PHP, Blade and (optionally) JS, TS, Vue and JSX, lists those missing from the source language, adds them with `--create` and reports unused ones.
- `translations:import` for JSON and PHP files, lang folders, and barryvdh/laravel-translation-manager's table.
- Optional *Suggest* button through the Laravel AI SDK, or your own `Suggester`.
- PHP API: `Translations::get()`, `set()`, `forget()`, `value()`, `entries()`, `missing()`, `progress()`, `pending()`, `locales()`, `addLocale()`, `export()`, `prune()`, `import()`, `scan()`, `check()`, `suggest()`.
- Opt-in JSON API for languages, entries and pending changes.
- `TranslationUpdated`, `TranslationsExported` and `LocaleAdded` events; `InvalidKey`, `InvalidLocale`, `LocaleAlreadyExists`, `ExportFailed`, `ImportFailed` and `SuggestionsUnavailable` exceptions.
- `Override::factory()` for tests in host apps.
- Laravel Boost guidelines.
- Ruvelo house style UI: light and dark, no build step, themable through CSS variables, usable inside your own layout.

[Unreleased]: https://github.com/Ruvelo/laravel-translations/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/Ruvelo/laravel-translations/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Ruvelo/laravel-translations/releases/tag/v1.0.0
