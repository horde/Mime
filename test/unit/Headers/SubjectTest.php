<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\Subject;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SubjectTest extends TestCase
{
    public function testBasic(): void
    {
        $s = new Subject('Subject', 'Hello World');
        $this->assertSame('Subject', $s->name());
        $this->assertSame('Hello World', $s->value());
        $this->assertSame('Hello World', (string) $s);
    }

    public function testSendEncodeAscii(): void
    {
        $s = new Subject('Subject', 'Hello');
        $encoded = $s->sendEncode();
        $this->assertCount(1, $encoded);
        $this->assertStringContainsString('Subject: ', $encoded[0]);
    }

    public function testHandles(): void
    {
        $this->assertSame(['subject'], Subject::handles());
    }
}
