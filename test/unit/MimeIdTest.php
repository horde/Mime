<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\MimeId;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MimeIdTest extends TestCase
{
    public function testStringable(): void
    {
        $id = new MimeId('1.2.3');
        $this->assertSame('1.2.3', (string) $id);
        $this->assertSame('1.2.3', $id->id);
    }

    #[DataProvider('downProvider')]
    public function testDown(string $input, ?string $expected, bool $noRfc822 = false): void
    {
        $id = new MimeId($input);
        $result = $id->down($noRfc822);

        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertNotNull($result);
            $this->assertSame($expected, $result->id);
        }
    }

    public static function downProvider(): array
    {
        return [
            ['1', '1.0', false],
            ['1', '1.1', true],
            ['1.0', '1.0.1', false],
            ['0', '0.1', false],
        ];
    }

    #[DataProvider('nextProvider')]
    public function testNext(string $input, string $expected): void
    {
        $id = new MimeId($input);
        $result = $id->next();
        $this->assertNotNull($result);
        $this->assertSame($expected, $result->id);
    }

    public static function nextProvider(): array
    {
        return [
            ['1', '2'],
            ['1.2', '1.3'],
            ['1.2.3', '1.2.4'],
        ];
    }

    #[DataProvider('prevProvider')]
    public function testPrev(string $input, ?string $expected): void
    {
        $id = new MimeId($input);
        $result = $id->prev();

        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertNotNull($result);
            $this->assertSame($expected, $result->id);
        }
    }

    public static function prevProvider(): array
    {
        return [
            ['2', '1'],
            ['1', null],
            ['0', null],
            ['1.3', '1.2'],
            ['1.1', null],
        ];
    }

    #[DataProvider('upProvider')]
    public function testUp(string $input, ?string $expected, bool $noRfc822 = false): void
    {
        $id = new MimeId($input);
        $result = $id->up($noRfc822);

        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertNotNull($result);
            $this->assertSame($expected, $result->id);
        }
    }

    public static function upProvider(): array
    {
        return [
            ['1', '0', false],
            ['1.2', '1', false],
            ['1.2.3', '1.2', false],
            ['0', null, false],
            ['1.0', null, false],
        ];
    }

    public function testIsChild(): void
    {
        $parent = new MimeId('1');

        $this->assertTrue($parent->isChild(new MimeId('1')));
        $this->assertTrue($parent->isChild(new MimeId('1.1')));
        $this->assertTrue($parent->isChild(new MimeId('1.2.3')));
        $this->assertFalse($parent->isChild(new MimeId('2')));
        $this->assertFalse($parent->isChild(new MimeId('12')));
    }

    public function testImmutability(): void
    {
        $id = new MimeId('1');
        $next = $id->next();

        $this->assertSame('1', $id->id);
        $this->assertNotSame($id, $next);
    }
}
