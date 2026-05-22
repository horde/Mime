<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde_Mime_Part;
use PHPUnit\Framework\TestCase;

/**
 * Tests the public getSize() contract of Horde_Mime_Part.
 *
 * getSize() internally uses Horde_Nls::getLocaleInfo() to format numbers
 * with locale-specific decimal/thousands separators.
 * @coversNothing
 */
class PartGetSizeTest extends TestCase
{
    public function testGetSizeReturnsFormattedKilobytes(): void
    {
        $part = new Horde_Mime_Part();
        $part->setBytes(10240);

        $result = $part->getSize();

        $this->assertIsString($result);
        $this->assertStringContainsString('10', $result);
    }

    public function testGetSizeReturnsZeroForEmptyPart(): void
    {
        $part = new Horde_Mime_Part();

        $result = $part->getSize();

        $this->assertEquals(0, $result);
    }

    public function testGetSizeWithLargeValue(): void
    {
        $part = new Horde_Mime_Part();
        $part->setBytes(1048576);

        $result = $part->getSize();

        $this->assertIsString($result);
        $this->assertStringContainsString('1', $result);
    }

    public function testGetSizeWithApprox(): void
    {
        $part = new Horde_Mime_Part();
        $part->setBytes(5120);

        $result = $part->getSize(true);

        $this->assertIsString($result);
        $this->assertStringContainsString('5', $result);
    }
}
