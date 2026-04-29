<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime\Headers;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

final class HeaderCollection implements IteratorAggregate, Countable
{
    /** @var array<string, HeaderElement> keyed by lowercase name */
    private array $headers = [];

    /** @var string[] original insertion order of lowercase names */
    private array $order = [];

    public function __construct(
        private readonly HeaderRegistry $registry = new HeaderRegistry(),
    ) {}

    public function get(string $name): ?HeaderElement
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @return HeaderElement[]
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->order as $key) {
            if (isset($this->headers[$key])) {
                $result[] = $this->headers[$key];
            }
        }

        return $result;
    }

    public function count(): int
    {
        return count($this->headers);
    }

    public function contentType(): ?ContentType
    {
        $h = $this->get('content-type');

        return ($h instanceof ContentType) ? $h : null;
    }

    public function contentDisposition(): ?ContentDisposition
    {
        $h = $this->get('content-disposition');

        return ($h instanceof ContentDisposition) ? $h : null;
    }

    public function contentTransferEncoding(): ?ContentTransferEncoding
    {
        $h = $this->get('content-transfer-encoding');

        return ($h instanceof ContentTransferEncoding) ? $h : null;
    }

    public function subject(): ?Subject
    {
        $h = $this->get('subject');

        return ($h instanceof Subject) ? $h : null;
    }

    public function date(): ?DateHeader
    {
        $h = $this->get('date');

        return ($h instanceof DateHeader) ? $h : null;
    }

    public function messageId(): ?MessageId
    {
        $h = $this->get('message-id');

        return ($h instanceof MessageId) ? $h : null;
    }

    public function from(): ?Addresses
    {
        $h = $this->get('from');

        return ($h instanceof Addresses) ? $h : null;
    }

    public function to(): ?Addresses
    {
        $h = $this->get('to');

        return ($h instanceof Addresses) ? $h : null;
    }

    public function with(HeaderElement $header): self
    {
        $clone = clone $this;
        $key = strtolower($header->name());

        if (!isset($clone->headers[$key])) {
            $clone->order[] = $key;
        }

        $clone->headers[$key] = $header;

        return $clone;
    }

    public function without(string $name): self
    {
        $clone = clone $this;
        $key = strtolower($name);
        unset($clone->headers[$key]);
        $clone->order = array_values(array_filter(
            $clone->order,
            fn(string $k) => $k !== $key,
        ));

        return $clone;
    }

    public function withRaw(string $name, string $value): self
    {
        $className = $this->registry->resolve($name);
        $header = self::instantiate($className, $name, $value);

        return $this->with($header);
    }

    public static function parse(string $raw, ?HeaderRegistry $registry = null): self
    {
        $registry ??= new HeaderRegistry();
        $collection = new self($registry);

        $lines = explode("\n", str_replace("\r\n", "\n", $raw));
        $currentName = null;
        $currentValue = '';

        foreach ($lines as $line) {
            if ($line === '') {
                break;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $currentName !== null) {
                $currentValue .= ' ' . ltrim($line);
                continue;
            }

            if ($currentName !== null) {
                $collection = $collection->addParsedHeader($currentName, $currentValue);
            }

            $colon = strpos($line, ':');
            if ($colon === false) {
                $currentName = null;
                continue;
            }

            $currentName = substr($line, 0, $colon);
            $currentValue = ltrim(substr($line, $colon + 1));
        }

        if ($currentName !== null) {
            $collection = $collection->addParsedHeader($currentName, $currentValue);
        }

        return $collection;
    }

    private function addParsedHeader(string $name, string $value): self
    {
        $key = strtolower($name);
        $className = $this->registry->resolve($name);

        if (isset($this->headers[$key])) {
            $existing = $this->headers[$key];
            if ($existing instanceof GenericHeader) {
                $clone = clone $this;
                $clone->headers[$key] = $existing->withValue($value);

                return $clone;
            }
            if ($existing instanceof Received) {
                $clone = clone $this;
                $clone->headers[$key] = $existing->withValue($value);

                return $clone;
            }
            if ($existing instanceof AddressesMulti) {
                $clone = clone $this;
                $clone->headers[$key] = $existing->withValue($value);

                return $clone;
            }

            return $this;
        }

        $header = self::instantiate($className, $name, $value);

        $clone = clone $this;
        $clone->headers[$key] = $header;
        $clone->order[] = $key;

        return $clone;
    }

    private static function instantiate(string $className, string $name, string $value): HeaderElement
    {
        if ($className === ContentType::class) {
            return ContentType::fromRaw($name, $value);
        }

        if ($className === ContentDisposition::class) {
            return ContentDisposition::fromRaw($name, $value);
        }

        if ($className === GenericHeader::class) {
            return new GenericHeader($name, [$value]);
        }

        if ($className === Received::class) {
            return new Received($name, [$value]);
        }

        if ($className === AddressesMulti::class) {
            return new AddressesMulti($name, [$value]);
        }

        return new $className($name, $value);
    }

    public function toString(string $eol = "\r\n"): string
    {
        $lines = [];
        foreach ($this->all() as $header) {
            foreach ($header->sendEncode() as $line) {
                $lines[] = $line;
            }
        }

        return implode($eol, $lines);
    }

    /**
     * @return array<string, string|string[]>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->all() as $header) {
            $name = $header->name();
            if ($header instanceof Received || $header instanceof AddressesMulti || $header instanceof GenericHeader) {
                $result[$name] = ($header instanceof GenericHeader)
                    ? $header->values()
                    : (method_exists($header, 'values') ? $header->values() : [$header->value()]);
            } else {
                $result[$name] = $header->value();
            }
        }

        return $result;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    public function registry(): HeaderRegistry
    {
        return $this->registry;
    }
}
