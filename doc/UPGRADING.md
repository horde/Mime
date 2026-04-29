# Upgrading to Horde\Mime 3.0

This document covers migrating from the legacy `Horde_Mime` (2.x, PSR-0 in `lib/`) to the modern `Horde\Mime` (3.x, PSR-4 in `src/`).

## Namespace Changes

All classes move from `Horde_Mime_*` to `Horde\Mime\*`.

| Legacy (2.x)                              | Modern (3.x)                                |
|-------------------------------------------|---------------------------------------------|
| `Horde_Mime_Part`                         | `Horde\Mime\Part`                           |
| `Horde_Mime_Mail`                         | `Horde\Mime\MessageBuilder`                 |
| `Horde_Mime_Mdn`                          | `Horde\Mime\Mdn`                            |
| `Horde_Mime_Magic`                        | `Horde\Mime\Magic`                          |
| `Horde_Mime_Related`                      | `Horde\Mime\Related`                        |
| `Horde_Mime_Id`                           | `Horde\Mime\MimeId`                         |
| `Horde_Mime_Headers`                      | `Horde\Mime\Headers\HeaderCollection`       |
| `Horde_Mime_Headers_ContentParam_ContentType` | `Horde\Mime\Headers\ContentType`       |
| `Horde_Mime_Headers_ContentParam_ContentDisposition` | `Horde\Mime\Headers\ContentDisposition` |
| `Horde_Mime_QuotedPrintable`              | `Horde\Mime\Encoding\QuotedPrintable`       |
| `Horde_Mime_Uudecode`                     | `Horde\Mime\Encoding\Uudecode`              |
| `Horde_Mime_ContentParam_Decode`          | `Horde\Mime\Encoding\ContentParamDecoder`   |
| `Horde_Mime_Exception`                    | `Horde\Mime\MimeException`                  |

## Exception Hierarchy

`Horde_Mime_Exception` extended `Horde_Exception_Wrapped` (PSR-0).
`Horde\Mime\MimeException` extends `Horde\Exception\HordeRuntimeException` (PSR-4).

Both are ultimately `RuntimeException` subclasses so existing `catch (RuntimeException $e)` blocks continue to work. Code that caught `Horde_Exception_Wrapped` specifically should switch to catching `MimeException` or `HordeRuntimeException`.

## Architecture Changes

### Part is Immutable

`Horde\Mime\Part` is a `final readonly` class. There are no setters.

**Before (mutable):**
```php
$part = new Horde_Mime_Part();
$part->setType('text/plain');
$part->setCharset('utf-8');
$part->setContents('Hello');
```

**After (builder pattern):**
```php
use Horde\Mime\PartBuilder;

$part = PartBuilder::text('Hello', 'plain', 'utf-8')->build();
```

To modify an existing Part, use `with*()` methods that return a new instance:
```php
$updated = $part->withBody('New content');
```

### No Transport Coupling

`Horde_Mime_Mail::send()` and `Horde_Mime_Part::send()` are removed. Message composition is now decoupled from sending.

**Before:**
```php
$mail = new Horde_Mime_Mail(['Subject' => 'Hi']);
$mail->setBody('Hello');
$mail->send($mailer);
```

**After:**
```php
use Horde\Mime\MessageBuilder;
use Horde\Mime\MessageRenderer;

$builder = new MessageBuilder();
$builder->setFrom('sender@example.com')
    ->setTo('rcpt@example.com')
    ->setSubject('Hi')
    ->setBody('Hello');

$composed = $builder->build();
$raw = MessageRenderer::render($composed->part, $composed->headers);
// Send $raw via your Transport of choice.
```

### Parsing

`Horde_Mime_Part::parseMessage()` becomes `MimeParser::parse()`.

**Before:**
```php
$part = Horde_Mime_Part::parseMessage($rawText, ['forcemime' => true]);
```

**After:**
```php
use Horde\Mime\MimeParser;
use Horde\Mime\MimeParserConfig;

$part = MimeParser::parse($rawText, new MimeParserConfig(forceMime: true));
```

### Headers are Immutable

`Horde\Mime\Headers\HeaderCollection` is immutable. All mutations return a new collection.

**Before:**
```php
$headers = Horde_Mime_Headers::parseHeaders($text);
$headers->addHeader('X-Custom', 'value');
```

**After:**
```php
use Horde\Mime\Headers\HeaderCollection;

$headers = HeaderCollection::parse($text);
$headers = $headers->withRaw('X-Custom', 'value');
```

### Transfer Encoding

Encoding constants become a backed enum.

**Before:**
```php
Horde_Mime_Part::ENCODE_7BIT  // 1
Horde_Mime_Part::ENCODE_8BIT  // 2
```

**After:**
```php
use Horde\Mime\TransferEncoding;

TransferEncoding::SevenBit
TransferEncoding::EightBit
TransferEncoding::Base64
TransferEncoding::QuotedPrintable
TransferEncoding::Binary
```

### MDN (Message Disposition Notification)

`Mdn::generate()` now returns a `ComposedMessage` instead of calling a transport.

**Before:**
```php
$mdn = new Horde_Mime_Mdn($headers);
$mdn->generate(true, true, 'displayed', 'test.local', $mailer);
```

**After:**
```php
use Horde\Mime\Mdn;

$mdn = new Mdn($headers);
$composed = $mdn->generate(
    manualAction: true,
    manualSending: true,
    type: 'displayed',
    reportingUa: 'test.local',
);
// Render and send $composed yourself.
```

### TextFormatter Strategy

Text body formatting (RFC 3676 format=flowed) is handled via a `TextFormatter` strategy.

Both `MessageBuilder` and `Mdn` accept an optional `TextFormatter` in their constructors which defaults to a `FlowedFormatter`. This default uses `Horde\Text\Flowed\TextFlowed` if available but otherwise does nothing.

```php
use Horde\Mime\MessageBuilder;
use Horde\Mime\TextFormatter;
use Horde\Mime\TextFormatResult;

// Custom formatter example:
$noopFormatter = new class implements TextFormatter {
    public function __invoke(string $text, string $charset): TextFormatResult
    {
        return new TextFormatResult($text);
    }
};

$builder = new MessageBuilder(textFormatter: $noopFormatter);
```

### Iteration

`Horde_Mime_Part` ArrayAccess and `RecursiveIterator` are replaced by `Part::iterate()`.

**Before:**
```php
foreach ($part as $id => $subpart) { ... }
$child = $part['2.1'];
```

**After:**
```php
foreach ($part->iterate() as $subpart) {
    $id = $subpart->metadata('mime-id'); // or use PartIterator::currentId()
}
$child = $part->child(0); // by index
```

### MimeId

`Horde_Mime::mimeIdArithmetic()` is now instance methods on `MimeId`.

**Before:**
```php
$next = Horde_Mime::mimeIdArithmetic('2.1', Horde_Mime_Id::ID_NEXT);
```

**After:**
```php
use Horde\Mime\MimeId;

$id = new MimeId('2.1');
$next = $id->next(); // returns MimeId('2.2')
```

## Removed Without Replacement

| Legacy Class/Method                   | Reason                                                      |
|---------------------------------------|-------------------------------------------------------------|
| `Horde_Mime_Translation`              | i18n is caller's responsibility                             |
| `Horde_Mime_Filter_Encoding`          | PHP stream filter replaced by `TransferEncoder`/`Decoder`   |
| `Horde_Mime_Part_Upgrade_V1`          | Legacy serialization migration.  No longer applicable       |
| `Horde_Mime_Part::serialize()`        | Immutable Part does not need PHP serialization              |
| `Horde_Mime_Part::send()`             | Use `MessageRenderer::render()` + Transport                 |
| `Horde_Mime::encode()`/`decode()`     | RFC 2047 lives in `horde/Mail` (`Rfc2047`)                  |
| `Horde_Mime_Headers_ThreadIndex`      | Non-standard MS Exchange header.  No known consumer         |
