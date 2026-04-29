<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\FlowedFormatter;
use Horde\Mime\TextFormatResult;
use Horde\Mime\TextFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowedFormatter::class)]
class FlowedFormatterTest extends TestCase
{
    public function testImplementsTextFormatter(): void
    {
        $formatter = new FlowedFormatter();
        $this->assertInstanceOf(TextFormatter::class, $formatter);
    }

    public function testReturnsTextFormatResult(): void
    {
        $formatter = new FlowedFormatter();
        $result = $formatter('Hello world', 'UTF-8');
        $this->assertInstanceOf(TextFormatResult::class, $result);
    }

    public function testTextIsNotEmpty(): void
    {
        $formatter = new FlowedFormatter();
        $result = $formatter('Hello world', 'UTF-8');
        $this->assertNotEmpty($result->text);
    }

    public function testIsInvokable(): void
    {
        $formatter = new FlowedFormatter();
        $result = ($formatter)('Test text', 'UTF-8');
        $this->assertInstanceOf(TextFormatResult::class, $result);
    }

    public function testFlowedFormattingApplied(): void
    {
        if (!class_exists(\Horde\Text\Flowed\TextFlowed::class)) {
            $this->markTestSkipped('horde/text_flowed not available.');
        }

        $formatter = new FlowedFormatter();
        $result = $formatter('Hello world', 'UTF-8');

        $this->assertSame('flowed', $result->params['format'] ?? null);
        $this->assertSame('yes', $result->params['delsp'] ?? null);
    }

    public function testNoopWhenTextFlowedUnavailable(): void
    {
        if (class_exists(\Horde\Text\Flowed\TextFlowed::class)) {
            $this->markTestSkipped('Text_Flowed IS available — cannot test no-op path.');
        }

        $formatter = new FlowedFormatter();
        $result = $formatter('Hello world', 'UTF-8');

        $this->assertSame('Hello world', $result->text);
        $this->assertEmpty($result->params);
    }
}
