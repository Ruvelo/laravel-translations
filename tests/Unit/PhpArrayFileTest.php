<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ruvelo\Translations\Export\JsonFile;
use Ruvelo\Translations\Export\PhpArrayFile;
use Ruvelo\Translations\Export\PhpArrayPrinter;

class PhpArrayFileTest extends TestCase
{
    private const FILE = <<<'PHP'
        <?php

        // Billing, as customers see it.
        return [
            /* Invoices */
            'invoice' => [
                'title' => 'Invoice :number', // shown in the tab
                'due'   => "Due on :date",
            ],
            'cancel' => 'Cancel',
        ];

        PHP;

    public function test_it_replaces_a_value_in_place(): void
    {
        $out = (new PhpArrayFile(self::FILE))->set(['invoice.due' => 'Payable le :date']);

        $this->assertSame(str_replace('"Due on :date"', "'Payable le :date'", self::FILE), $out);
    }

    public function test_it_keeps_comments_and_alignment(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(['cancel' => 'Résilier']);

        $this->assertStringContainsString('// Billing, as customers see it.', $out);
        $this->assertStringContainsString('/* Invoices */', $out);
        $this->assertStringContainsString("'title' => 'Invoice :number', // shown in the tab", $out);
        $this->assertStringContainsString("'due'   => \"Due on :date\",", $out);
        $this->assertStringContainsString("'cancel' => 'Résilier',", $out);
    }

    public function test_new_keys_go_after_their_neighbour_in_the_source_order(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(
            ['invoice.paid' => 'Payée', 'refund' => 'Rembourser'],
            ['invoice.title', 'invoice.due', 'invoice.paid', 'refund', 'cancel'],
        );

        $this->assertSame(<<<'PHP'
            <?php

            // Billing, as customers see it.
            return [
                /* Invoices */
                'invoice' => [
                    'title' => 'Invoice :number', // shown in the tab
                    'due'   => "Due on :date",
                    'paid' => 'Payée',
                ],
                'refund' => 'Rembourser',
                'cancel' => 'Cancel',
            ];

            PHP, $out);
    }

    public function test_new_nested_keys_become_nested_arrays(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(['seats.one' => 'Un siège', 'seats.many' => ':count sièges']);

        $this->assertStringContainsString(<<<'PHP'
                'cancel' => 'Cancel',
                'seats' => [
                    'one' => 'Un siège',
                    'many' => ':count sièges',
                ],
            ];
            PHP, $out);
        $this->assertSame(['one' => 'Un siège', 'many' => ':count sièges'], $this->evaluate($out)['seats']);
    }

    public function test_a_key_first_in_the_source_goes_first(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(['heading' => 'Facturation'], ['heading', 'invoice.title', 'cancel']);

        $this->assertSame(['heading', 'invoice', 'cancel'], array_keys($this->evaluate($out)));
    }

    public function test_it_handles_a_last_element_without_a_trailing_comma(): void
    {
        $out = (string) (new PhpArrayFile("<?php\nreturn array(\n  'a' => 'A',\n  'b' => 'B'\n);\n"))->set(['c' => 'C']);

        $this->assertSame("<?php\nreturn array(\n  'a' => 'A',\n  'b' => 'B',\n  'c' => 'C'\n);\n", $out);
    }

    public function test_it_fills_an_empty_array(): void
    {
        $out = (string) (new PhpArrayFile("<?php\n\nreturn [];\n"))->set(['a' => 'A', 'b.c' => 'C']);

        $this->assertSame(['a' => 'A', 'b' => ['c' => 'C']], $this->evaluate($out));
        $this->assertStringContainsString("return [\n    'a' => 'A',\n", $out);
    }

    public function test_a_string_turns_into_an_array_when_a_child_key_is_set(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(['cancel.title' => 'Résilier']);

        $this->assertSame(['title' => 'Résilier'], $this->evaluate($out)['cancel']);
    }

    public function test_quotes_and_backslashes_are_escaped(): void
    {
        $out = (string) (new PhpArrayFile(self::FILE))->set(['cancel' => "Résilier l'abonnement \\ maintenant"]);

        $this->assertSame("Résilier l'abonnement \\ maintenant", $this->evaluate($out)['cancel']);
    }

    public function test_it_gives_up_on_files_it_cannot_follow(): void
    {
        $this->assertNull((new PhpArrayFile("<?php\nreturn array_merge(require 'x.php', ['a' => 'b']);\n"))->set(['a' => 'c']));
        $this->assertNull((new PhpArrayFile("<?php\nreturn [...\$base, 'a' => 'b'];\n"))->set(['a' => 'c']));
        $this->assertNull((new PhpArrayFile("<?php\nreturn [KEY => 'b'];\n"))->set(['a' => 'c']));
        $this->assertNull((new PhpArrayFile("<?php\necho 'hi';\n"))->set(['a' => 'c']));
    }

    public function test_it_skips_returns_inside_functions(): void
    {
        $code = "<?php\n\$f = function () { return ['x' => 'y']; };\nreturn [\n    'a' => 'A',\n];\n";

        $this->assertSame(['a' => 'B'], $this->evaluate((string) (new PhpArrayFile($code))->set(['a' => 'B'])));
    }

    public function test_the_printer_writes_laravel_style_files(): void
    {
        $this->assertSame(<<<'PHP'
            <?php

            return [
                'a' => 'It\'s',
                'b' => [
                    'c' => 'C',
                ],
            ];

            PHP, PhpArrayPrinter::file(['a' => "It's", 'b' => ['c' => 'C']]));
    }

    public function test_json_keeps_order_indentation_and_newline(): void
    {
        $json = "{\n  \"Pay now\": \"Payer\",\n  \"Sign out\": \"Déconnexion\"\n}\n";
        $out = (string) JsonFile::set($json, ['Pay now' => 'Payer maintenant', 'Download' => 'Télécharger'], ['Download', 'Pay now', 'Sign out']);

        $this->assertSame("{\n  \"Download\": \"Télécharger\",\n  \"Pay now\": \"Payer maintenant\",\n  \"Sign out\": \"Déconnexion\"\n}\n", $out);
    }

    public function test_json_inserts_after_the_neighbour(): void
    {
        $out = (string) JsonFile::set("{\n    \"A\": \"a\",\n    \"C\": \"c\"\n}", ['B' => 'b'], ['A', 'B', 'C']);

        $this->assertSame("{\n    \"A\": \"a\",\n    \"B\": \"b\",\n    \"C\": \"c\"\n}", $out);
    }

    public function test_json_new_file_and_invalid_file(): void
    {
        $this->assertSame("{\n    \"0\": \"zero\",\n    \"A/B\": \"é\"\n}\n", JsonFile::set(null, ['0' => 'zero', 'A/B' => 'é']));
        $this->assertNull(JsonFile::set('{not json', ['a' => 'b']));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function evaluate(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'lang').'.php';
        file_put_contents($file, $code);

        try {
            return require $file;
        } finally {
            unlink($file);
        }
    }
}
