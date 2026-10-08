<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Name
    |--------------------------------------------------------------------------
    |
    | Shown in the header and in page titles.
    |
    */

    'name' => env('TRANSLATIONS_NAME', 'Translations'),

    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    |
    | Edits made in the browser are stored in the database and applied on top
    | of your lang files at runtime, so they go live without touching the
    | server's filesystem. Set to false to stop applying them (the editor
    | still works; nothing shows on the site until you export).
    |
    */

    'enabled' => (bool) env('TRANSLATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | `source_locale` is the language you write in; every other locale is
    | measured against it. null uses your app's fallback locale.
    |
    | `locales` null detects them from the lang folder (folders and .json
    | files) plus any added in the browser. Give a list to fix it instead.
    |
    | `names` overrides the display name of a locale (e.g. 'pt_BR' => 'Português
    | (Brasil)'). Without ext-intl, locales show by their code.
    |
    */

    'source_locale' => null,

    'locales' => null,

    'names' => [],

    /*
    |--------------------------------------------------------------------------
    | Lang files
    |--------------------------------------------------------------------------
    |
    | `lang_path` null uses your app's lang path. Package translations under
    | lang/vendor/{package}/{locale} are included when `vendor` is true;
    | `namespaces` adds packages that ship their own translations (registered
    | with loadTranslationsFrom) so you can translate them too. `ignore_groups`
    | hides whole files from the editor, e.g. ['validation'].
    |
    */

    'lang_path' => null,

    'vendor' => true,

    'namespaces' => [],

    'ignore_groups' => [],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | The editor is served under `path` (e.g. /translations). Every route
    | needs a signed-in user who passes the `translations-edit` gate. Nobody
    | passes until you define it. Guests go to your `login` route if you have
    | one, or get a 403. Set `routes` to false to register your own.
    |
    */

    'routes' => true,

    'path' => env('TRANSLATIONS_PATH', 'translations'),

    'domain' => null,

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Flags
    |--------------------------------------------------------------------------
    |
    | Round language flags (bundled, from circle-flags) beside each language.
    | A locale's region wins when a flag exists for it (pt_BR → pt-br), then
    | its language. Map a locale to another flag here, or set to false to
    | show none.
    |
    */

    'flags' => [
        // 'en' => 'en-us',
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | null uses the package's own page. Set a view name (e.g. 'layouts.app')
    | to render inside your app's layout instead: the editor fills the
    | `section` you name, and pushes its <head> tags to a `translations-head`
    | stack if your layout has one.
    |
    */

    'layout' => null,

    'section' => 'content',

    'per_page' => 50,

    /*
    |--------------------------------------------------------------------------
    | Editing in context
    |--------------------------------------------------------------------------
    |
    | Editors get a small "Edit translations" button on every page of your
    | app. Turned on, it opens a panel listing the strings used to render the
    | page, each with an editor. `inject` adds the button to every HTML page
    | through the `web` middleware group; set it to false and place
    | <x-translations::toolbar /> before </body> in your layout instead.
    |
    | Recording which keys a page uses means swapping in a translator that
    | remembers them. Set `enabled` to false if another package replaces
    | Laravel's translator and you'd rather keep it.
    |
    */

    'in_context' => [
        'enabled' => true,
        'inject' => true,
        'max_keys' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Overrides are compiled per locale and file, and cached until one of
    | them changes. `store` null uses your default cache store.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Missing key scanner
    |--------------------------------------------------------------------------
    |
    | `translations:scan` looks for __(), trans(), trans_choice(), @lang,
    | @choice and Lang::get() in these folders. Turn on `js` to also read
    | .js/.ts/.vue/.jsx/.tsx files for the `js_functions` (e.g. $t('...')).
    | Groups in `framework_groups` are never reported as unused: Laravel
    | reads them itself.
    |
    */

    'scan' => [
        'paths' => [
            'app',
            'resources/views',
            'routes',
        ],
        'js' => false,
        'js_paths' => [
            'resources/js',
        ],
        'js_functions' => ['$t', 't', '$tc', 'tc', 'trans', '__', 'wTrans', 'i18n.t'],
        'framework_groups' => ['validation', 'auth', 'pagination', 'passwords'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggestions
    |--------------------------------------------------------------------------
    |
    | With the Laravel AI SDK (laravel/ai) installed and configured, editors
    | get a "Suggest" button that drafts a translation from the source text.
    | Drafts are never saved on their own. `provider` and `model` null use
    | the SDK's defaults.
    |
    */

    'suggestions' => [
        'enabled' => (bool) env('TRANSLATIONS_SUGGESTIONS', true),
        'provider' => null,
        'model' => null,
        'per_minute' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | A REST API for locales, entries and pending changes under `prefix`,
    | off by default. Every request needs `middleware` and the
    | `translations-edit` gate. The default middleware expects Laravel
    | Sanctum; use your own guard if not.
    |
    */

    'api' => [
        'enabled' => (bool) env('TRANSLATIONS_API', false),
        'prefix' => 'api/translations',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Two tables: {prefix}overrides and {prefix}locales. Set
    | `run_migrations` to false if you publish the migration and run it
    | yourself.
    |
    */

    'table_prefix' => 'translations_',

    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Editors
    |--------------------------------------------------------------------------
    |
    | Who changes are attributed to, and the attribute shown as their name.
    |
    */

    'user_model' => null,

    'user_name_attribute' => 'name',

];
