# Horde\Mime

MIME message handling library for PHP 8.2+.

Provides immutable value objects for building, parsing and rendering RFC 2822/2045 MIME messages.

## Installation

```bash
composer require horde/mime
```

## Quick Start

```php
use Horde\Mime\MessageBuilder;
use Horde\Mime\MessageRenderer;
use Horde\Mime\MimeParser;
use Horde\Mime\PartBuilder;

// Compose a message
$builder = new MessageBuilder();
$builder->setFrom('sender@example.com')
    ->setTo('rcpt@example.com')
    ->setSubject('Hello')
    ->setBody('Plain text body', flowed: true)
    ->addAttachment('/path/to/file.pdf');

$composed = $builder->build();
$raw = MessageRenderer::render($composed->part, $composed->headers);

// Parse a message
$part = MimeParser::parse($rawMessage);
echo $part->fullType();        // e.g. "multipart/mixed"
echo $part->children[0]->body; // first child's decoded body

// Build parts directly
$part = PartBuilder::text('Hello world')->build();
$html = PartBuilder::html('<p>Hello</p>')->build();
$attachment = PartBuilder::attachment($data, 'report.pdf', 'application/pdf')->build();
```

## Documentation

- [Upgrading from 2.x to 3.x](doc/UPGRADING.md)

## License

LGPL 2.1 - see [LICENSE](LICENSE) for details.
