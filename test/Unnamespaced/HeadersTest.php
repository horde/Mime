<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Test\Unnamespaced;

use PHPUnit\Framework\TestCase;
use Horde_Mime_Headers;
use Horde_Mime_Headers_ContentParam_ContentType;
use Horde_Mime_Headers_ContentParam_ContentDisposition;
use Horde_Mail_Rfc822;
use Horde_Stream_Existing;
use Horde_Mime_Headers_Addresses;
use Horde_Mime_Headers_Element_Single;

/**
 * Tests for the Horde_Mime_Headers class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 * @coversNothing
 */
class HeadersTest extends TestCase
{
    public function testClone()
    {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader('To', 'foo@example.com');
        $hdrs->addHeader('Resent-To', 'foo2@example.com');
        $hdrs->addHeader('Resent-To', 'foo3@example.com');

        $ct = new Horde_Mime_Headers_ContentParam_ContentType(
            null,
            'text/plain; charset="iso-8859-1"'
        );
        $hdrs->addHeaderOb($ct);

        $cd = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            null,
            'attachment; filename="foo"'
        );
        $hdrs->addHeaderOb($cd);

        $hdrs2 = clone $hdrs;

        $hdrs->addHeader('To', 'bar@example.com');
        $hdrs->addHeader('Resent-To', 'bar2@example.com');
        $ct['charset'] = 'utf-8';
        $cd['filename'] = 'bar';

        $this->assertEquals(
            'foo@example.com',
            strval($hdrs2['To'])
        );
        $this->assertEquals(
            ['foo2@example.com', 'foo3@example.com'],
            $hdrs2['Resent-To']->value
        );
        $this->assertEquals(
            ['charset' => 'iso-8859-1'],
            $hdrs2['Content-Type']->params
        );
        $this->assertEquals(
            ['filename' => 'foo'],
            $hdrs2['Content-Disposition']->params
        );
    }

    /**
     * @dataProvider serializeProvider
     */
    public function testSerialize($header, $value)
    {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader($header, $value);

        $hdrs2 = unserialize(serialize($hdrs));

        /* @deprecated */
        $this->assertEquals(
            $value,
            $hdrs2->getValue($header)
        );

        $this->assertequals(
            $value,
            strval($hdrs2[$header])
        );
    }

    public static function serializeProvider()
    {
        return [
            [
                'Subject', 'My Subject',
            ],
            [
                'To', 'recipient@example.com',
            ],
            [
                'Cc', 'null@example.com',
            ],
            [
                'Bcc', 'invisible@example.com',
            ],
            [
                'From', 'sender@example.com',
            ],
        ];
    }

    /**
     * @dataProvider normalHeaderDecodeProvider
     */
    public function testNormalHeaderDecode($header, $value, $decoded)
    {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader($header, $value);

        /* @deprecated */
        $this->assertEquals(
            $decoded,
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            $decoded,
            $hdrs[$header]->value_single
        );
    }

    public static function normalHeaderDecodeProvider()
    {
        return [
            [
                'Test',
                '=?iso-8859-15?b?VmVyc2nzbg==?=',
                'Versión',
            ],
            [
                'To',
                '=?utf-8?B?IklsZ2EginVwbGluc2thIg==?= <foo@example.com>',
                'Ilga Šuplinska <foo@example.com>',
            ],
            [
                'From',
                'Firstname =?utf-8?b?U2Vjb25kw5Es?= Third <correct.email@host.com>',
                '"Firstname SecondÑ, Third" <correct.email@host.com>',
            ],
            [
                'From',
                '=?utf-8?B?TGFuZXNza29nLCBKw7hyZ2Vu?= <Jorgen.Lanesskog@marinit.no>',
                '"Lanesskog, Jørgen" <Jorgen.Lanesskog@marinit.no>',
            ],
        ];
    }

    /**
     * @dataProvider contentParamHeaderDecodeProvider
     */
    public function testContentParamHeaderDecode(
        $header,
        $value,
        $decode_value,
        $decode_params
    ) {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader($header, $value);

        $this->assertEquals(
            $decode_value,
            $hdrs[$header]->value
        );

        ksort($decode_params);
        $params = $hdrs[$header]->params;
        ksort($params);

        $this->assertEquals(
            $decode_params,
            $params
        );
    }

    public static function contentParamHeaderDecodeProvider()
    {
        return [
            [
                'Content-Type',
                'text/plain; name="=?iso-8859-15?b?VmVyc2nzbg==?="',
                'text/plain',
                [
                    'name' => 'Versión',
                ],
            ],
            [
                'Content-Disposition',
                "attachment; size=147502;\n filename*=utf-8''Factura%20n%C2%BA%2010.pdf",
                'attachment',
                [
                    'size' => '147502',
                    'filename' => 'Factura nº 10.pdf',
                ],
            ],
        ];
    }

    /**
     * @dataProvider headerAutoDetectCharsetProvider
     */
    public function testHeaderAutoDetectCharset($header, $value, $decoded)
    {
        $hdrs = Horde_Mime_Headers::parseHeaders($value);

        /* @deprecated */
        $this->assertEquals(
            $decoded,
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            $decoded,
            $hdrs[$header]->value_single
        );
    }

    public static function headerAutoDetectCharsetProvider()
    {
        return [
            [
                'Test',
                // This string is in Windows-1252
                'Test: ' . base64_decode('UnVubmVyc5IgQWxlcnQh='),
                'Runners’ Alert!',
            ],
        ];
    }

    /**
     * @dataProvider headerEncodeProvider
     */
    public function testHeaderEncode(
        $header,
        $values,
        $charset,
        $encoded
    ) {
        $hdrs = new Horde_Mime_Headers();
        foreach ($values as $val) {
            $hdrs->addHeader($header, $val);
        }

        $hdr_encode = $hdrs[$header]->sendEncode([
            'charset' => $charset,
        ]);

        $this->assertEquals(
            $encoded,
            $hdr_encode
        );

        $hdr_array = $hdrs->toArray([
            'charset' => $charset,
        ]);

        $this->assertEquals(
            (count($encoded) > 1) ? $encoded : reset($encoded),
            $hdr_array[$header]
        );
    }

    public static function headerEncodeProvider()
    {
        return [
            /* Single address header */
            [
                'To',
                [
                    'Empfänger <recipient@example.com>',
                ],
                'iso-8859-1',
                [
                    '=?iso-8859-1?b?RW1wZuRuZ2Vy?= <recipient@example.com>',
                ],
            ],
            /* Multiple address header */
            [
                'Resent-To',
                [
                    'Empfänger <recipient@example.com>',
                    'Foo <foo@example.com>',
                ],
                'iso-8859-1',
                [
                    '=?iso-8859-1?b?RW1wZuRuZ2Vy?= <recipient@example.com>',
                    'Foo <foo@example.com>',
                ],
            ],
            /* Bug #13814 */
            [
                'Content-Description',
                [
                    'AüA',
                ],
                'utf-8',
                [
                    '=?utf-8?b?QcO8QQ==?=',
                ],
            ],
        ];
    }

    public function testMultipleContentType()
    {
        $hdrs = Horde_Mime_Headers::parseHeaders(
            "Content-Type: multipart/mixed\n"
            . "Content-Type: multipart/mixed\n"
        );

        $this->assertIsString(
            $hdrs->getValue('content-type', Horde_Mime_Headers::VALUE_BASE)
        );

        $this->assertIsString(
            $hdrs['content-type']->value
        );
    }

    /**
     * @dataProvider multivalueHeadersProvider
     */
    public function testMultivalueHeaders($header, $in, $expected)
    {
        $hdrs = Horde_Mime_Headers::parseHeaders($in);

        /* @deprecated */
        $this->assertEquals(
            $expected,
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            $expected,
            $hdrs['to']->value
        );
    }

    public static function multivalueHeadersProvider()
    {
        $expected = 'recipient1@example.com, recipient2@example.com';

        return [
            [
                'To',
                'To: recipient1@example.com, recipient2@example.com',
                $expected,
            ],
            [
                'To',
                "To: recipient1@example.com\nTo: recipient2@example.com",
                $expected,
            ],
        ];
    }

    /**
     * @dataProvider addHeaderWithGroupProvider
     */
    public function testAddHeaderWithGroup($header, $email)
    {
        $rfc822 = new Horde_Mail_Rfc822();
        $ob = $rfc822->parseAddressList($email);

        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader($header, $ob);

        /* @deprecated */
        $this->assertEquals(
            $email,
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            $email,
            $hdrs[$header]->value
        );
    }

    public static function addHeaderWithGroupProvider()
    {
        return [
            [
                'To',
                'Test: foo@example.com, bar@example.com;',
            ],
        ];
    }

    /**
     * @dataProvider unencodeMimeHeaderProvider
     */
    public function testUnencodedMimeHeader($header, $in, $decoded)
    {
        $hdrs = Horde_Mime_Headers::parseHeaders($in);

        /* @deprecated */
        $this->assertEquals(
            $decoded,
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            $decoded,
            $hdrs[$header]->value
        );
    }

    public static function unencodeMimeHeaderProvider()
    {
        return [
            [
                'From',
                // The header is base64 encoded to preserve charset data.
                base64_decode('RnJvbTogwqkgVklBR1JBIMKuIE9mZmljaWFsIFNpdGUgPGZvb0BleGFtcGxlLmNvbT4='),
                '© VIAGRA ® Official Site <foo@example.com>',
            ],
        ];
    }

    /**
     * @dataProvider parseContentDispositionHeaderWithUtf8DataProvider
     */
    public function testParseContentDispositionHeaderWithUtf8Data(
        $header,
        $parameter,
        $msg,
        $value
    ) {
        $hdrs = Horde_Mime_Headers::parseHeaders($msg);

        /* @deprecated */
        $cd_params = $hdrs->getValue(
            $header,
            $hdrs::VALUE_PARAMS
        );
        $this->assertEquals(
            $value,
            $cd_params[$parameter]
        );

        $this->assertEquals(
            $value,
            $hdrs[$header]->params[$parameter]
        );
    }

    public static function parseContentDispositionHeaderWithUtf8DataProvider()
    {
        return [
            [
                'content-disposition',
                'filename',
                file_get_contents(__DIR__ . '/fixtures/sample_msg_eai.txt'),
                'blåbærsyltetøy',
            ],
        ];
    }

    public function testCaseInsensitiveContentParameters()
    {
        $hdr = 'Content-Type: multipart/mixed; BOUNDARY="foo"';
        $hdrs = Horde_Mime_Headers::parseHeaders($hdr);

        /* @deprecated */
        $c_params =  $hdrs->getValue(
            'Content-Type',
            $hdrs::VALUE_PARAMS
        );
        $this->assertEquals(
            'foo',
            $c_params['boundary']
        );

        $this->assertEquals(
            'foo',
            $hdrs['content-type']['boundary']
        );
    }

    public function testParseEaiAddresses()
    {
        /* Simple message. */
        $msg = file_get_contents(__DIR__ . '/fixtures/sample_msg_eai_2.txt');
        $hdrs = Horde_Mime_Headers::parseHeaders($msg);

        /* @deprecated */
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs->getValue('from')
        );

        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs['from']->value
        );

        /* Message with EAI addresses in 2 fields, and another header that
         * contains a string (that looks like an address). */
        $msg = file_get_contents(__DIR__ . '/fixtures/sample_msg_eai_4.txt');
        $hdrs = Horde_Mime_Headers::parseHeaders($msg);

        /* @deprecated */
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs->getValue('from')
        );
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs->getValue('cc')
        );
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs->getValue('signed-off-by')
        );

        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs['from']->value
        );
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs['cc']->value
        );
        $this->assertEquals(
            'Jøran Øygårdvær <jøran@example.com>',
            $hdrs['signed-off-by']->value_single
        );
    }

    /**
     * @dataProvider undisclosedHeaderParsingProvider
     */
    public function testUndisclosedHeaderParsing($header, $value)
    {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader($header, $value);

        /* @deprecated */
        $this->assertEquals(
            '',
            $hdrs->getValue($header)
        );

        $this->assertEquals(
            '',
            $hdrs[$header]->value
        );
    }

    public static function undisclosedHeaderParsingProvider()
    {
        return [
            ['To', 'undisclosed-recipients'],
            ['To', 'undisclosed-recipients:'],
            ['To', 'undisclosed-recipients:;'],
        ];
    }

    public function testMultipleToAddresses()
    {
        $msg = file_get_contents(__DIR__ . '/fixtures/multiple_to.txt');
        $hdrs = Horde_Mime_Headers::parseHeaders($msg);

        /* @deprecated */
        $this->assertNotEmpty($hdrs->getValue('To'));

        $this->assertNotEmpty($hdrs['To']->value);
    }

    public function testBug12189()
    {
        $msg = file_get_contents(__DIR__ . '/fixtures/header_trailing_ws.txt');
        $hdrs = Horde_Mime_Headers::parseHeaders($msg);

        /* @deprecated */
        $this->assertNotNull($hdrs->getValue('From'));

        $this->assertNotNull($hdrs['From']->value);
    }

    public function testParseHeadersGivingStreamResource()
    {
        $fp = fopen(__DIR__ . '/fixtures/multiple_to.txt', 'r');
        $hdrs = Horde_Mime_Headers::parseHeaders($fp);
        fclose($fp);

        /* @deprecated */
        $this->assertNotEmpty($hdrs->getValue('To'));

        $this->assertNotEmpty($hdrs['To']->value);
    }

    public function testParseHeadersGivingHordeStreamObject()
    {
        $stream = new Horde_Stream_Existing([
            'stream' => fopen(__DIR__ . '/fixtures/multiple_to.txt', 'r'),
        ]);
        $hdrs = Horde_Mime_Headers::parseHeaders($stream);

        /* @deprecated */
        $this->assertNotEmpty($hdrs->getValue('To'));

        $this->assertNotEmpty($hdrs['To']);
    }

    public function testParseHeadersBlankSubject()
    {
        $stream = new Horde_Stream_Existing([
            'stream' => fopen(__DIR__ . '/fixtures/blank_subject.txt', 'r'),
        ]);
        $hdrs = Horde_Mime_Headers::parseHeaders($stream);

        /* @deprecated */
        $this->assertNotEmpty($hdrs->getValue('To'));

        $this->assertNotEmpty($hdrs['To']);
    }

    /**
     * @dataProvider multiplePriorityHeadersProvider
     */
    public function testMultiplePriorityHeaders($header, $data, $value)
    {
        $hdrs = Horde_Mime_Headers::parseHeaders($data);

        /* @deprecated */
        $this->assertIsString(
            $hdrs->getValue($header, Horde_Mime_Headers::VALUE_BASE)
        );
        $this->assertEquals(
            $value,
            $hdrs->getValue($header)
        );

        $this->assertIsString(
            $hdrs[$header]->value_single
        );
        $this->assertEquals(
            $value,
            $hdrs[$header]->value_single
        );

    }

    public static function multiplePriorityHeadersProvider()
    {
        return [
            [
                'Importance',
                "Importance: High\nImportance: Low\n",
                'High',
            ],
            [
                'X-priority',
                "X-Priority: 1\nX-priority: 5\n",
                '1',
            ],
        ];
    }

    public function testInvalidHeaderParsing()
    {
        $data = file_get_contents(__DIR__ . '/fixtures/invalid_hdr.txt');

        $hdrs = Horde_Mime_Headers::parseHeaders($data);

        $this->assertEquals(
            'foo@example.com',
            strval($hdrs['to'])
        );

        $this->assertNull($hdrs['From test2@example.com  Sun Apr  5 09']);
    }

    /**
     * @dataProvider addHeaderObProvider
     */
    public function testAddHeaderOb($ob, $valid)
    {
        $hdrs = new Horde_Mime_Headers();
        if (!$valid) {
            $this->expectException('InvalidArgumentException');
            $hdrs->addHeaderOb($ob, true);
        } else {
            $hdrs->addHeaderOb($ob, true);
            $this->assertEquals(
                $ob,
                $hdrs[$ob->name]
            );
        }
    }

    public static function addHeaderObProvider()
    {
        return [
            [
                new Horde_Mime_Headers_Addresses('To', 'foo@example.com'),
                true,
            ],
            [
                new Horde_Mime_Headers_Element_Single('To', 'foo@example.com'),
                false,
            ],
        ];
    }

    /**
     * @dataProvider headerGenerationProvider
     */
    public function testHeaderGeneration($label, $data, $class)
    {
        $hdrs = new Horde_Mime_Headers();

        $this->assertNull($hdrs[$label]);

        $hdrs->addHeader($label, $data);

        $ob = $hdrs[$label];

        $this->assertNotNull($ob);
        $this->assertInstanceOf($class, $ob);
    }

    public static function headerGenerationProvider()
    {
        return [
            [
                'content-disposition',
                'inline',
                'Horde_Mime_Headers_ContentParam_ContentDisposition',
            ],
            [
                'content-language',
                'en',
                'Horde_Mime_Headers_ContentLanguage',
            ],
            [
                'content-type',
                'text/plain',
                'Horde_Mime_Headers_ContentParam_ContentType',
            ],
        ];
    }

    public function testBug14381()
    {
        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader('Date', 'Sat, 22 May 2016 01:41:04 0000');
        $this->assertEquals($hdrs->getValue('Date'), 'Sat, 22 May 2016 01:41:04 +0000');

        $hdrs = new Horde_Mime_Headers();
        $hdrs->addHeader('Date', 'Sat, 22 May 2016 01:41:04 +0000');
        $this->assertEquals($hdrs->getValue('Date'), 'Sat, 22 May 2016 01:41:04 +0000');
    }

}
