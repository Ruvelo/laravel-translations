<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruvelo\Translations\Support\Placeholders;

class PlaceholdersTest extends TestCase
{
    public function test_a_faithful_translation_has_no_warnings(): void
    {
        $this->assertSame([], Placeholders::check('Welcome back, :name!', 'Bon retour, :name !'));
        $this->assertSame([], Placeholders::check('{0} No plans|{1} One plan|[2,*] :count plans', '{0} Aucune offre|{1} Une offre|[2,*] :count offres'));
        $this->assertSame([], Placeholders::check('Hello {name}', 'Hallo {name}'));
        $this->assertSame([], Placeholders::check('<strong>:amount</strong> due', '<strong>:amount</strong> à payer'));
    }

    public function test_a_dropped_placeholder_is_reported(): void
    {
        $this->assertSame(['Missing :name, which the source text uses.'], Placeholders::check('Welcome back, :name!', 'Bon retour !'));
        $this->assertSame(['Missing {count}, which the source text uses.'], Placeholders::check('{count} seats', 'Sièges'));
    }

    public function test_an_added_placeholder_is_reported(): void
    {
        $this->assertSame(['Adds :amount, which the source text doesn\'t have.'], Placeholders::check('Paid', 'Payé :amount'));
    }

    public function test_case_variants_count_as_the_same_placeholder(): void
    {
        // Laravel fills :name, :Name and :NAME from the same value.
        $this->assertSame([], Placeholders::check('Hi :name', 'Salut :Name'));
        $this->assertSame([], Placeholders::check('Hi :NAME', 'Salut :name'));
    }

    public function test_colons_in_ordinary_text_are_not_placeholders(): void
    {
        $this->assertSame([], Placeholders::check('Visit https://halyard.test at 10:30', 'Visitez https://halyard.test à 10h30'));
        $this->assertSame([], Placeholders::check('Note: billing is monthly', 'Remarque : facturation mensuelle'));
    }

    public function test_plural_forms_are_checked(): void
    {
        $this->assertSame(
            ['The source text has 3 plural forms separated by |; this has one.'],
            Placeholders::check('{0} No plans|{1} One plan|[2,*] Plans', 'Offres'),
        );
        $this->assertSame(
            ['This has plural forms separated by |, but the source text has none.'],
            Placeholders::check('Plans', 'Offre|Offres'),
        );
    }

    public function test_differing_ranges_are_reported(): void
    {
        $this->assertSame(
            ['The plural ranges differ: the source text uses {0} {1} [2,*], this uses {0} [1,*].'],
            Placeholders::check('{0} None|{1} One|[2,*] Many', '{0} Aucun|[1,*] Plusieurs'),
        );
        // Plain pipes on both sides are fine whatever the count: languages differ.
        $this->assertSame([], Placeholders::check('apple|apples', 'jabłko|jabłka|jabłek'));
    }

    public function test_range_markers_are_not_placeholders(): void
    {
        $this->assertSame([], Placeholders::check('{0} Nothing|[1,*] :count items', '{0} Rien|[1,*] :count articles'));
    }

    public function test_html_tags_are_checked(): void
    {
        $this->assertSame(['Missing the <a> tag.'], Placeholders::check('Read <a href="/terms">the terms</a>', 'Lisez les conditions'));
        $this->assertSame(['Adds a <b> tag, which the source text doesn\'t have.'], Placeholders::check('Read the terms', 'Lisez <b>les</b> conditions'));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function placeholderLists(): array
    {
        return [
            'colon' => [':name and :count', [':name' => ':name', ':count' => ':count']],
            'braces' => ['{name} {count}', ['{name}' => '{name}', '{count}' => '{count}']],
            'ranges ignored' => ['{0} none|[1,*] :n', [':n' => ':n']],
            'double colon ignored' => ['App::class', []],
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[DataProvider('placeholderLists')]
    public function test_it_lists_placeholders(string $text, array $expected): void
    {
        $this->assertSame($expected, Placeholders::placeholders($text));
    }
}
