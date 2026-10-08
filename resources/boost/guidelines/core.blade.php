## Laravel Translations (ruvelo/laravel-translations)

A translation manager. The app's `lang/` files stay the source of truth; edits made in the browser (at `/translations`, or in context on any page) are stored as database overrides, applied at runtime on top of the files, and written back into `lang/` by `php artisan translations:export`.

### Rules

- Keep using `__()`, `trans()`, `trans_choice()` and `@lang` as usual. Do not read the `translations_overrides` table yourself: Laravel's translator already applies it.
- Add new strings to the source locale's lang files (or run `php artisan translations:scan --create`), never only to the database.
- Write translation keys as literal strings so the scanner can find them: `__('billing.invoice.title')`, not `__($key)`.
- Access is the `translations-edit` gate. Nobody passes until it is defined:

@verbatim
<code-snippet name="Who may translate" lang="php">
// AppServiceProvider::boot()
Gate::define('translations-edit', fn (User $user) => $user->is_admin);
</code-snippet>
@endverbatim

- Change translations through `Ruvelo\Translations\Translations`, the one write path. It validates, clears the cache and fires `TranslationUpdated`.

@verbatim
<code-snippet name="PHP API" lang="php">
use Ruvelo\Translations\Translations;

Translations::set('fr', 'billing.invoice.title', 'Facture :number', $user); // live at once
Translations::set('fr', 'Pay now', 'Payer maintenant');                    // a JSON key
Translations::forget('fr', 'billing.invoice.title');                       // back to the lang file
Translations::progress('fr')->percent();
Translations::missing('de');                                               // Collection<Entry>
Translations::check('Hi :name', 'Salut');                                  // placeholder warnings
Translations::export(dryRun: true)->files;                                 // diffs, nothing written
</code-snippet>
@endverbatim

### Commands

- `php artisan translations:export [--dry-run] [--locale=fr] [--prune]`: write pending edits into `lang/`, keeping comments and key order. Commit the result.
- `php artisan translations:prune`: delete overrides the lang files now hold (run after deploying an export).
- `php artisan translations:scan [--js] [--create] [--unused]`: keys used in code but missing from the source locale.
- `php artisan translations:import path/to/fr.json` or `--translation-manager`: bring translations in as pending changes.

### In-context editing

Editors get an "Edit translations" button on every HTML page (injected into the `web` group). If `translations.in_context.inject` is false, put `<x-translations::toolbar />` last in the layout's `<body>`. After a save the panel reloads the page; Livewire or Inertia apps can listen for the `translations:saved` DOM event and call `preventDefault()` to re-render instead.

### Tests

@verbatim
<code-snippet name="In your tests" lang="php">
use Ruvelo\Translations\Models\Override;

Override::factory()->locale('fr')->key('billing.title')->value('Facturation')->create();
$this->assertSame('Facturation', __('billing.title', [], 'fr'));
</code-snippet>
@endverbatim
