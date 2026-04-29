<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\Addresses;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\GenericHeader;
use Horde\Mime\Headers\HeaderRegistry;
use Horde\Mime\Headers\HeaderElement;
use Horde\Mime\Headers\Subject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HeaderRegistryTest extends TestCase
{
    public function testBuiltInHeaders(): void
    {
        $registry = new HeaderRegistry();

        $this->assertSame(Subject::class, $registry->resolve('subject'));
        $this->assertSame(Subject::class, $registry->resolve('Subject'));
        $this->assertSame(ContentType::class, $registry->resolve('content-type'));
        $this->assertSame(Addresses::class, $registry->resolve('from'));
        $this->assertSame(Addresses::class, $registry->resolve('to'));
    }

    public function testUnknownFallsBackToGeneric(): void
    {
        $registry = new HeaderRegistry();

        $this->assertSame(GenericHeader::class, $registry->resolve('x-custom'));
        $this->assertSame(GenericHeader::class, $registry->resolve('X-Nonexistent'));
    }

    public function testCustomRegistration(): void
    {
        $registry = new HeaderRegistry();
        $registry->register('x-custom', Subject::class);

        $this->assertSame(Subject::class, $registry->resolve('x-custom'));
        $this->assertSame(Subject::class, $registry->resolve('X-Custom'));
    }

    public function testRegisterRejectsNonHeaderElement(): void
    {
        $registry = new HeaderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->register('x-bad', \stdClass::class);
    }

    public function testAllReturnsMap(): void
    {
        $registry = new HeaderRegistry();
        $all = $registry->all();

        $this->assertArrayHasKey('subject', $all);
        $this->assertArrayHasKey('from', $all);
        $this->assertArrayHasKey('content-type', $all);
    }
}
