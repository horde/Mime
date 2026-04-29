<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\MimeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MimeExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $e = new MimeException('test');
        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('test', $e->getMessage());
    }
}
