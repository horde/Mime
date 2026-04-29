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

use InvalidArgumentException;

final class HeaderRegistry
{
    /** @var array<string, class-string<HeaderElement>> */
    private array $map = [];

    /** @var list<class-string<HeaderElement>> */
    private static array $builtIn = [
        Addresses::class,
        AddressesMulti::class,
        ContentDescription::class,
        ContentDisposition::class,
        ContentId::class,
        ContentLanguage::class,
        ContentTransferEncoding::class,
        ContentType::class,
        DateHeader::class,
        Identification::class,
        MessageId::class,
        MimeHeader::class,
        MimeVersion::class,
        Received::class,
        Subject::class,
        UserAgent::class,
    ];

    public function __construct()
    {
        foreach (self::$builtIn as $class) {
            foreach ($class::handles() as $name) {
                $this->map[strtolower($name)] = $class;
            }
        }
    }

    /**
     * @param class-string<HeaderElement> $className
     */
    public function register(string $headerName, string $className): void
    {
        if (!is_a($className, HeaderElement::class, true)) {
            throw new InvalidArgumentException(
                sprintf('%s does not implement %s', $className, HeaderElement::class),
            );
        }

        $this->map[strtolower($headerName)] = $className;
    }

    /**
     * @return class-string<HeaderElement>
     */
    public function resolve(string $headerName): string
    {
        return $this->map[strtolower($headerName)] ?? GenericHeader::class;
    }

    /**
     * @return array<string, class-string<HeaderElement>>
     */
    public function all(): array
    {
        return $this->map;
    }
}
