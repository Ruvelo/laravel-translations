<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Key;

class KeyTest extends TestCase
{
    public function test_file_keys(): void
    {
        $key = Key::group('billing', 'invoice.title');

        $this->assertSame('billing.invoice.title', $key->full());
        $this->assertSame('billing', $key->file());
        $this->assertFalse($key->isJson());
        $this->assertSame(['namespace' => '*', 'group' => 'billing', 'key' => 'invoice.title'], $key->toArray());
    }

    public function test_package_keys(): void
    {
        $key = Key::parse('courier::messages.sent');

        $this->assertSame('courier', $key->namespace);
        $this->assertSame('courier::messages.sent', (string) $key);
        $this->assertSame('courier::messages', $key->file());
    }

    public function test_json_keys(): void
    {
        $key = Key::json('Pay now. Or later.');

        $this->assertTrue($key->isJson());
        $this->assertSame('Pay now. Or later.', $key->full());
        $this->assertSame('*', $key->file());
        $this->assertTrue($key->equals(Key::fromParts('*', '*', 'Pay now. Or later.')));
        $this->assertFalse(Key::json('pay.now')->equals(Key::group('pay', 'now')));
    }

    public function test_sub_folder_groups(): void
    {
        $this->assertSame('admin/users.title', Key::group('admin/users', 'title')->full());
    }

    public function test_bad_keys_are_refused(): void
    {
        foreach ([
            fn () => Key::group('../etc', 'passwd'),
            fn () => Key::group('billing', ''),
            fn () => Key::group('billing', 'a..b'),
            fn () => Key::group('billing', 'x', 'bad name'),
            fn () => Key::json('   '),
            fn () => Key::parse('no-dot'),
        ] as $make) {
            try {
                $make();
                $this->fail('Expected InvalidKey');
            } catch (InvalidKey $e) {
                $this->assertInstanceOf(TranslationsException::class, $e);
            }
        }
    }
}
