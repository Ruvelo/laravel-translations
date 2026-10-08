<?php

declare(strict_types=1);

/*
 * Builds the demo published at https://ruvelo.github.io/laravel-translations/.
 *
 * Copies Halyard's lang files (demo/lang) somewhere safe, seeds a few edits
 * made in the browser, renders every page through the package's real routes
 * and views, and writes the HTML out as static files. Saving, reverting and
 * filtering then run in the browser (demo/demo.js): nothing is stored.
 *
 *   php demo/build.php <site-dir> [<shots-dir>]
 *
 * <shots-dir>, when given, receives the pages the screenshots are taken of,
 * without the demo banner.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Foundation\Application;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Translations;
use Ruvelo\Translations\TranslationsServiceProvider;

require __DIR__.'/../vendor/autoload.php';

const HOST = 'https://ruvelo.github.io';
const PATH = 'laravel-translations';
const REPO = 'https://github.com/Ruvelo/laravel-translations';

$site = rtrim($argv[1] ?? __DIR__.'/../build/site', '/');
$shots = isset($argv[2]) ? rtrim($argv[2], '/') : null;

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => HOST,
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'TRANSLATIONS_PATH' => PATH,
    'TRANSLATIONS_NAME' => 'Halyard Translations',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

class DemoUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

// Work on a copy: nothing here writes files, but keep demo/lang pristine anyway.
$lang = sys_get_temp_dir().'/ruvelo-translations-demo-'.getmypid().'/lang';
(new Filesystem)->copyDirectory(__DIR__.'/lang', $lang);
register_shutdown_function(fn () => (new Filesystem)->deleteDirectory(dirname($lang)));

$app = Application::create(basePath: null, options: ['extra' => ['providers' => [TranslationsServiceProvider::class]]]);
$app->useLangPath($lang);
$app['config']->set('auth.providers.users.model', DemoUser::class);
$app['config']->set('app.locale', 'en');
$app['config']->set('app.fallback_locale', 'en');
// One page per language: the browser filters it.
$app['config']->set('translations.per_page', 1000);
$app['config']->set('translations.in_context.inject', false);
// No AI provider in the demo, so no "Suggest" button.
$app['config']->set('translations.suggestions.enabled', false);
// The Halyard app ships translations for the Inbox package too.
$app['translator']->addNamespace('inbox', $lang.'/vendor/inbox');

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
$app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);

// --- Seed -------------------------------------------------------------------

$people = [];
foreach (['Maya Okafor', 'Tom Reyes', 'Inès Laurent', 'Kenji Mori'] as $name) {
    $first = strtok($name, ' ');
    $people[$first] = DemoUser::forceCreate([
        'name' => $name,
        'email' => strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $first)).'@halyard.test',
        'password' => bin2hex(random_bytes(16)),
    ]);
}

// The whole team translates.
Gate::define('translations-edit', fn (DemoUser $user) => str_ends_with($user->email, '@halyard.test'));

$now = Carbon::now();
// Edits made in the browser and not exported yet: [how long ago, who, locale, key, text].
foreach ([
    ['2 days', 'Maya', 'en', 'billing.cancel_confirm', 'Cancel your subscription? You keep full access until :date.'],
    ['1 day', 'Inès', 'fr', 'billing.plan.renews', 'Renouvellement le :date'],
    ['5 hours', 'Kenji', 'de', 'billing.status.overdue', 'Überfällig'],
    ['3 hours', 'Kenji', 'de', 'invoices.reminder.body', 'Hallo :name, Rechnung :number über :amount war am :date fällig.'],
    ['2 hours', 'Inès', 'fr', 'Your next invoice of :amount is due on :date.', 'Votre prochaine facture est à régler le :date.'],
    ['40 minutes', 'Tom', 'es', 'invoices.mark_paid', 'Marcar como pagada'],
    ['20 minutes', 'Tom', 'nl', 'Pay now', 'Nu betalen'],
] as [$ago, $who, $locale, $key, $text]) {
    Carbon::setTestNow($now->copy()->modify("-{$ago}"));
    Translations::set($locale, $key, $text, $people[$who]);
}
Carbon::setTestNow();

// --- Render -----------------------------------------------------------------

Route::middleware('web')->get(PATH.'/halyard', function () {
    app()->setLocale(request()->query('locale', 'fr'));
    session()->put('translations.edit_mode', request()->query('panel', '1') === '1');

    return view()->file(__DIR__.'/app.blade.php');
});

$http = $app->make(HttpKernel::class);

$fetch = function (string $path, ?User $as = null) use ($app, $http): string {
    $app['auth']->forgetGuards();
    if ($as !== null) {
        $app['auth']->guard()->setUser($as);
    }

    $url = HOST.'/'.PATH.($path === '' ? '' : '/'.$path);
    $request = Request::create($url);
    $response = $http->handle($request);
    $http->terminate($request, $response);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("GET {$url} answered {$response->getStatusCode()}");
    }

    // Root-relative links, so the same files work on Pages and on localhost.
    return str_replace(HOST.'/', '/', (string) $response->getContent());
};

$banner = '<div style="background:#3d4eff;color:#fff;font:500 .875rem/1.4 ui-sans-serif,system-ui,-apple-system,\'Segoe UI\',Roboto,sans-serif;padding:.55rem 16px;text-align:center;position:relative;z-index:50">'
    .'You’re looking at a read-only demo of <a href="'.REPO.'" style="color:inherit;font-weight:700">ruvelo/laravel-translations</a>. '
    .'Edits work in your browser and aren’t saved. '
    .'<a href="/'.PATH.'/halyard/" style="color:inherit">Edit a page in context</a> · '
    .'<a href="'.REPO.'#readme" style="color:inherit">Read the docs</a></div>';

$demoScript = '<script src="/'.PATH.'/assets/demo.js" defer></script>';

$write = function (string $root, string $path, string $html, bool $withBanner = true) use ($banner, $demoScript): void {
    $html = preg_replace('/<\/head>/', $demoScript.'</head>', $html, 1) ?? $html;
    if ($withBanner) {
        $html = preg_replace('/<body( class="trans")?>/', '$0'.$banner, $html, 1) ?? $html;
    }
    $file = $root.'/'.($path === '' ? '' : rawurldecode($path).'/').'index.html';
    is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
    file_put_contents($file, $html);
};

$maya = $people['Maya'];
$paths = ['', 'changes'];
foreach (Translations::locales() as $locale) {
    $paths[] = $locale;
}

foreach ($paths as $path) {
    $write($site, $path, $fetch($path, $maya));
}

// The Halyard page, with the panel open; demo.js swaps in the closed
// button (rendered here too) when you press Done.
$inApp = $fetch('halyard', $maya);
$closed = $fetch('halyard?panel=0', $maya);
preg_match('/<form class="trans-tb-toggle".*?<\/form>/s', $closed, $toggle);
$inApp = str_replace('</body>', '<template id="trans-demo-toggle">'.($toggle[0] ?? '').'</template></body>', $inApp);
$write($site, 'halyard', $inApp);

is_dir($site.'/assets') || mkdir($site.'/assets', 0777, true);
copy(__DIR__.'/demo.js', $site.'/assets/demo.js');

if ($shots !== null) {
    // Put the caret in the row with the placeholder warning, so the shot
    // shows the editor as someone uses it.
    $focus = fn (string $key) => '<script>addEventListener("load",()=>{const t=document.querySelector("#'.Translations::entry('fr', $key)->id().' textarea");t.closest("tr").classList.add("is-focus")})</script>';

    foreach ([
        'overview' => $fetch('', $maya),
        'editor' => str_replace('</body>', $focus('Your next invoice of :amount is due on :date.').'</body>', $fetch('fr', $maya)),
        'missing' => $fetch('de', $maya),
        'changes' => $fetch('changes', $maya),
        'dark' => $fetch('fr', $maya),
    ] as $name => $html) {
        $write($shots, $name, $html, false);
    }
    $write($shots, 'panel', $inApp, false);
}

fwrite(STDOUT, sprintf(
    "Built %d demo pages (%d languages, %d pending changes) into %s\n",
    count($paths) + 1,
    count(Translations::locales()),
    Override::query()->count(),
    $site,
));
