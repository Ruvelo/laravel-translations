<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Ruvelo\Translations\Key;
use Ruvelo\Translations\Scanner\Scanner;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class ScannerTest extends TestCase
{
    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        $this->code = (string) realpath(__DIR__.'/../Fixtures/code');
    }

    public function test_php_and_blade_patterns(): void
    {
        $keys = array_keys(Translations::scan([$this->code])->used);

        foreach ([
            'billing.invoice.title',      // __('...')
            'billing.invoice.paid',       // trans("...")
            'billing.plans',              // trans_choice() and @choice
            'billing.refund',             // Lang::get()
            'billing.seats',              // \Illuminate\Support\Facades\Lang::choice()
            'Download receipt',           // a JSON key
            "It's overdue",               // escaped quote
            'courier::messages.sent',     // a package key
            'Pay now',                    // {{ __() }} in Blade
            'billing.cancel',             // @lang
            'Welcome back, :name!',       // double quotes in Blade
        ] as $key) {
            $this->assertContains($key, $keys, "Expected to find {$key}");
        }

        // Not translation calls, or not literal.
        $this->assertNotContains('not.a.key', $keys);
        $this->assertNotContains('status.', $keys);
        // JS files are only read when asked.
        $this->assertNotContains('Export CSV', $keys);
    }

    public function test_js_patterns_when_turned_on(): void
    {
        $keys = array_keys(Translations::scan([$this->code], js: true)->used);

        $this->assertContains('billing.invoice.due', $keys);   // $t('...')
        $this->assertContains('Export CSV', $keys);            // t("...")
        $this->assertContains('Open settings', $keys);         // $t(`...`)
        $this->assertContains('billing.trial_ends', $keys);    // i18n.t('...')
        $this->assertNotContains('not.a.key', $keys);          // format('...')
        $this->assertNotContains('status.${state}', $keys);    // a template with a variable
    }

    public function test_locations_are_reported(): void
    {
        $result = Translations::scan([$this->code]);

        $this->assertSame(['tests/Fixtures/code/Controller.php:11'], array_map(
            fn (string $location) => preg_replace('#^.*?(tests/Fixtures)#', '$1', $location),
            $result->locations('billing.invoice.title'),
        ));
    }

    public function test_missing_keys(): void
    {
        $missing = array_map(fn (Key $key) => $key->full(), Translations::scan([$this->code], js: true)->missing);
        sort($missing);

        $this->assertSame([
            'Download receipt',
            'Export CSV',
            "It's overdue",
            'Open settings',
            'billing.refund',
            'billing.seats',
            'billing.trial_ends',
        ], $missing);
    }

    public function test_package_keys_are_looked_up_in_lang_vendor_and_the_package(): void
    {
        file_put_contents(dirname($this->lang).'/tmp-scan.php', "<?php __('courier::messages.sent'); __('courier::messages.failed');");

        try {
            $missing = fn () => array_map(fn (Key $key) => $key->full(), Translations::scan([dirname($this->lang).'/tmp-scan.php'])->missing);

            // lang/vendor/courier has "sent"; "failed" is only in the package.
            $this->assertSame(['courier::messages.failed'], $missing());

            config()->set('translations.namespaces', ['courier']);
            app('translator')->addNamespace('courier', __DIR__.'/../Fixtures/packages/courier/lang');
            Translations::catalogue()->refresh();
            $this->assertSame([], $missing());
        } finally {
            unlink(dirname($this->lang).'/tmp-scan.php');
        }
    }

    public function test_unused_keys(): void
    {
        $unused = array_map(fn (Key $key) => $key->full(), Translations::scan([$this->code])->unused);

        // billing.invoice.due is only used from JS, and Sign out nowhere.
        $this->assertContains('billing.invoice.due', $unused);
        $this->assertContains('Sign out', $unused);
        $this->assertNotContains('billing.invoice.title', $unused);
        // Framework groups are never reported.
        $this->assertNotContains('auth.failed', $unused);
    }

    public function test_keys_built_at_runtime_hide_their_family_from_the_unused_list(): void
    {
        file_put_contents($this->lang.'/en/status.php', "<?php\n\nreturn ['paid' => 'Paid', 'open' => 'Open'];\n");
        Translations::catalogue()->refresh();

        $result = Translations::scan([$this->code]);

        $this->assertContains('status.', $result->prefixes);
        $unused = array_map(fn (Key $key) => $key->full(), $result->unused);
        $this->assertNotContains('status.paid', $unused);
    }

    public function test_find_works_on_a_string(): void
    {
        $found = app(Scanner::class)->find("<?php echo __('a.b'), __(\"Hello\\n\"), __(\$x), __('x' . \$y);");

        $this->assertSame(['a.b', "Hello\n"], array_column($found, 0));
    }
}
