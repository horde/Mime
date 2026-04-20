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
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Mime_Headers_ContentParam;

/**
 * Tests for the Horde_Mime_Headers_ContentParam class.
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
class ContentParamTest extends TestCase
{
    /**
     * @dataProvider encodeProvider
     */
    #[DataProvider('encodeProvider')]
    public function testEncode($params, $opts, $expected)
    {
        $cp = new Horde_Mime_Headers_ContentParam('NOT_USED', $params);

        ksort($expected);
        $params = $cp->encode($opts);
        ksort($params);

        $this->assertEquals(
            $expected,
            $params
        );
    }

    public static function encodeProvider()
    {
        return [
            [
                [
                    'bar' => 'foo',
                    'test' => str_repeat('a', 100) . '.txt',
                ],
                [
                    'broken_rfc2231' => true,
                ],
                [
                    'bar' => 'foo',
                    'test' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.txt',
                    'test*0' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'test*1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.txt',
                ],
            ],
            [
                [
                    'bar' => 'foo',
                    'test' => str_repeat('a', 100) . '.txt',
                ],
                [
                    'broken_rfc2231' => false,
                ],
                [
                    'bar' => 'foo',
                    'test*0' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'test*1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.txt',
                ],
            ],
            [
                [
                    'foo' => "\x01",
                ],
                [],
                [
                    'foo' => "\"\x01\"",
                ],
            ],
            // Bug #12127 (part 1)
            [
                [
                    'foo' => 'test',
                ],
                [
                    'broken_rfc2231' => true,
                    'charset' => 'UTF-16LE',
                ],
                [
                    'foo' => 'test',
                ],
            ],
            // Bug #12127 (part 2)
            [
                [
                    'foo' => 'ā',
                ],
                [
                    'broken_rfc2231' => true,
                    'charset' => 'UTF-16LE',
                ],
                [
                    'foo*' => "utf-16le''%01%01",
                    'foo' => '"=?utf-16le?b?AQE=?="',
                ],
            ],
        ];
    }

    /**
     * @dataProvider decodeProvider
     */
    #[DataProvider('decodeProvider')]
    public function testDecode($in, $val_expected, $params_expected)
    {
        $cp = new Horde_Mime_Headers_ContentParam('NOT_USED', $in);

        $this->assertEquals(
            $val_expected,
            $cp->value
        );


        ksort($params_expected);
        $params = $cp->params;
        ksort($params);

        $this->assertEquals(
            $params_expected,
            $params
        );
    }

    public static function decodeProvider()
    {
        return [
            [
                'foo',
                'foo',
                [],
            ],
            [
                'foo=bar',
                null,
                [
                    'foo' => 'bar',
                ],
            ],
            [
                'test ; foo = bar ; baz = "goo"',
                'test',
                [
                    'baz' => 'goo',
                    'foo' => 'bar',
                ],
            ],
            [
                'test ; foo*1=B; foo*0="A"; foo*3=D; foo*2="C";foo*5=F;'
                . 'foo*4="E"; foo*7=H; foo*6="G"; foo*9=J; foo*8=I; foo*11=L; '
                . 'bar  =  Z  ;  foo*10=K;',
                'test',
                [
                    'bar' => 'Z',
                    'foo' => 'ABCDEFGHIJKL',
                ],
            ],
            [
                "attachment; size=147502;\n filename*=utf-8''Factura%20n%C2%BA%2010.pdf",
                'attachment',
                [
                    'size' => '147502',
                    'filename' => 'Factura nº 10.pdf',
                ],
            ],
            // Bug #13587
            [
                "multipart/mixed; boundary=\"EPOC32-8'4Lqb7RwmJkJ+8bx'NRLMC2SXt1Ls'Gfpd0RMtxgP6JQFKj\"",
                'multipart/mixed',
                [
                    'boundary' => "EPOC32-8'4Lqb7RwmJkJ+8bx'NRLMC2SXt1Ls'Gfpd0RMtxgP6JQFKj",
                ],
            ],
            // Gmail
            [
                // Content-Disposition
                "attachment;\n filename=\"=?UTF-8?Q?Vantagens_da_Caixa_para_Empresas_e_alterac=CC=A7a=CC=83o_do_prec=CC=A7?=\n =?UTF-8?Q?a=CC=81rio=2Epdf?=\"",
                'attachment',
                [
                    'filename' => 'Vantagens da Caixa para Empresas e alteração do preçário.pdf',
                ],
            ],
            [
                // Content-Type
                "application/pdf;\nname=\"=?UTF-8?Q?Vantagens_da_Caixa_para_Empresas_e_alterac=CC=A7a=CC=83o_do_prec=CC=A7?=\n =?UTF-8?Q?a=CC=81rio=2Epdf?=\"",
                'application/pdf',
                [
                    'name' => 'Vantagens da Caixa para Empresas e alteração do preçário.pdf',
                ],
            ],
            // mail.app
            // Note: filename/name parameter value is NOT the same UTF-8
            // string as the Gmail examples above; character length is the
            // same, but Gmail byte-length is 4 bytes longer (they are using
            // different Unicode points to display the 4 non-ASCII chars)
            [
                // Content-Disposition
                "inline;\n filename*=iso-8859-1''Vantagens%20da%20Caixa%20para%20Empresas%20e%20altera%E7%E3o%20do%20pre%E7%E1rio.pdf",
                'inline',
                [
                    'filename' => 'Vantagens da Caixa para Empresas e alteração do preçário.pdf',
                ],
            ],
            [
                // Content-Type
                "application/pdf;\n name=\"=?iso-8859-1?Q?Vantagens_da_Caixa_para_Empresas_e_altera=E7=E3o_?=\n =?iso-8859-1?Q?do_pre=E7=E1rio=2Epdf?=\"",
                'application/pdf',
                [
                    'name' => 'Vantagens da Caixa para Empresas e alteração do preçário.pdf',
                ],
            ],
            // Params with different cases (params are case-insensitive)
            [
                "kEy*1=b; KEY*0=a; key*2=c",
                null,
                [
                    'key' => 'abc',
                ],
            ],
            // Adapted from Dovecot's src/lib-mail/test-rfc2231-parser.c
            [
                "key4*=us-ascii''foo"
                . "; key*2=ba%"
                . "; key2*0=a"
                . "; key3*0*=us-ascii'en'xyz"
                . "; key*0=\"foo\""
                . "; key2*1*=b%25"
                . "; key3*1=plop%"
                . "; key*1=baz",
                null,
                [
                    'key' => 'foobazba%',
                    'key2' => 'ab%',
                    'key3' => 'xyzplop%',
                    'key4' => 'foo',
                ],
            ],
        ];
    }

}
