<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\Uudecode;
use Horde\Mime\Encoding\UudecodeEntry;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UudecodeTest extends TestCase
{
    private const SAMPLE = "begin 644 test.txt\n#0V%T\n`\nend";

    public function testDecodeSingle(): void
    {
        $uu = new Uudecode(self::SAMPLE);
        $this->assertCount(1, $uu);
        $entry = $uu->first();
        $this->assertInstanceOf(UudecodeEntry::class, $entry);
        $this->assertSame('test.txt', $entry->name);
        $this->assertSame('644', $entry->permissions);
        $this->assertSame('Cat', $entry->data);
    }

    public function testDecodeEmpty(): void
    {
        $uu = new Uudecode('no uuencoded data here');
        $this->assertCount(0, $uu);
        $this->assertNull($uu->first());
    }

    public function testDecodeMultiple(): void
    {
        $input = self::SAMPLE . "\n" . "begin 755 other.bin\n#0V%T\n`\nend";
        $uu = new Uudecode($input);
        $this->assertCount(2, $uu);
    }

    public function testIterator(): void
    {
        $uu = new Uudecode(self::SAMPLE);
        $entries = iterator_to_array($uu);
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(UudecodeEntry::class, $entries[0]);
    }

    public function testEntryIsReadonly(): void
    {
        $entry = new UudecodeEntry('data', 'file.txt', '644');
        $this->assertSame('data', $entry->data);
        $this->assertSame('file.txt', $entry->name);
        $this->assertSame('644', $entry->permissions);
    }
}
