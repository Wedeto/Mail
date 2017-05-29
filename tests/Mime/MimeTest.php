<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Mime\Mime was adapted from Zend\Mime\Mime
The modifications are: Copyright 2017, Egbert van der Wal <wedeto at pointpro dot nl>

The original source code is copyright Zend Technologies USA Inc. The original
licence information is included below.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer. Redistributions in binary form
must reproduce the above copyright notice, this list of conditions and the
following disclaimer in the documentation and/or other materials provided with
the distribution. Neither the name of Zend or Rogue Wave Software, nor the
names of its contributors may be used to endorse or promote products derived
from this software without specific prior written permission. THIS SOFTWARE IS
PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 

 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\Mail\Mime;

use PHPUnit\Framework\TestCase;

use InvalidArgumentException;

/**
 * @covers Wedeto\Mail\Mime\Mime
 */
class MimeTest extends TestCase
{
    /** Stores the original set timezone */
    protected $_originaltimezone;

    public function setUp()
    {
        $this->_originaltimezone = date_default_timezone_get();
        mb_internal_encoding("UTF-8");
    }

    public function tearDown()
    {
        date_default_timezone_set($this->_originaltimezone);
        mb_internal_encoding("UTF-8");
    }

    public function testBoundary()
    {
        // check boundary for uniqueness
        $m1 = new Mime();
        $m2 = new Mime();
        $this->assertNotEquals($m1->boundary(), $m2->boundary());

        // check instantiating with arbitrary boundary string
        $myBoundary = 'mySpecificBoundary';
        $m3 = new Mime($myBoundary);
        $this->assertEquals($m3->boundary(), $myBoundary);
    }

    public function testIsPrintable_notPrintable()
    {
        $this->assertFalse(Mime::isPrintable('Test with special chars: �����'));
    }

    public function testIsPrintable_isPrintable()
    {
        $this->assertTrue(Mime::isPrintable('Test without special chars'));
    }

    public function testQP()
    {
        $text = "This is a cool Test Text with special chars: ����\n"
              . "and with multiple lines���� some of the Lines are long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long and with ����";

        $qp = Mime::encode($text, 'Q');
        $this->assertEquals($text, quoted_printable_decode($qp));

        $qp = Mime::encode($text, 'Q', '', Mime::LINEEND);
        $this->assertEquals($text, mb_decode_mimeheader($qp));
    }

    public function testQuotedPrintableNoDotAtBeginningOfLine()
    {
        $text = str_repeat('a', Mime::LINELENGTH - 1) . '.bbb';
        $qp = Mime::encode($text, 'Q');

        $expected = str_repeat('a', Mime::LINELENGTH - 2) . "=\na.bbb";
        $this->assertEquals($expected, $qp);

        $text = str_repeat('.', Mime::LINELENGTH + 1);
        $qp = Mime::encode($text, 'Q');

        $expected = str_repeat('.', Mime::LINELENGTH - 1) . "=\n..";
        $this->assertEquals($expected, $qp);
    }

    public function testQuotedPrintableDoesNotBreakOctets()
    {
        $text = str_repeat('a', Mime::LINELENGTH - 3) . '=.bbb';
        $qp = Mime::encode($text, 'Q');

        $expected = str_repeat('a', Mime::LINELENGTH - 3) . "=\n=3D.bbb";
        $this->assertEquals($expected, $qp);

        // Other breaking point
        $text = str_repeat('a', Mime::LINELENGTH - 2) . '=.bbb';
        $qp = Mime::encode($text, 'Q');

        $expected = str_repeat('a', Mime::LINELENGTH - 2) . "=\n=3D.bbb";
        $this->assertEquals($expected, $qp);
    }

    public function testBase64()
    {
        $content = str_repeat("\x88\xAA\xAF\xBF\x29\x88\xAA\xAF\xBF\x29\x88\xAA\xAF", 4);
        $encoded = Mime::encode($content, 'B');
        $this->assertEquals($content, base64_decode($encoded));
    }

    public function testZf1058WhitespaceAtEndOfBodyCausesInfiniteLoop()
    {
        $text   = "my body\r\n\r\n...after two newlines\r\n ";
        $result = quoted_printable_decode(Mime::encode($text, 'Q'));
        $this->assertContains("my body\r\n\r\n...after two newlines", $result, $result);
    }

    /**
     * @dataProvider dataTestEncodeMailHeaderQuotedPrintable
     */
    public function testEncodeMailHeaderQuotedPrintable($str, $result)
    {
        $this->assertEquals($result, Mime::encode($str, 'Q', '', Mime::LINEEND, false));
    }

    public static function dataTestEncodeMailHeaderQuotedPrintable()
    {
        return [
            ["äöü", "=?UTF-8?Q?=C3=A4=C3=B6=C3=BC?="],
            ["äöü ", "=?UTF-8?Q?=C3=A4=C3=B6=C3=BC=20?="],
            ["Gimme more €", "Gimme more =?UTF-8?Q?=E2=82=AC?="],
            ["Alle meine Entchen schwimmen in dem See, schwimmen in dem See, Köpfchen in das Wasser, Schwänzchen in die Höh!", "Alle meine Entchen schwimmen in dem See, schwimmen in dem See,\n =?UTF-8?Q?K=C3=B6pfchen=20in=20das=20Wasser=2C=20Schw=C3=A4nzchen=20in=20?=\n =?UTF-8?Q?die=20H=C3=B6h!?="],
            ["ääääääääääääääääääääääääääääääääää", "=?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4?="],
            ["A0", "A0"],
            ["äääääääääääääää ä", "=?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=20=C3=A4?="],
            ["äääääääääääääää äääääääääääääää", "=?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=20=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?="],
            ["ä äääääääääääääää", "=?UTF-8?Q?=C3=A4=20=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?=\n =?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?="],
        ];
    }

    /**
     * @dataProvider dataTestEncodeMailHeaderBase64
     */
    public function testEncodeMailHeaderBase64($str, $result)
    {
        $this->assertEquals($result, Mime::encode($str, 'B', '', Mime::LINEEND, false));
    }

    public static function dataTestEncodeMailHeaderBase64()
    {
        return [
            ["äöü", "=?UTF-8?B?w6TDtsO8?="],
            ["Alle meine Entchen schwimmen in dem See, schwimmen in dem See, Köpfchen in das Wasser, Schwänzchen in die Höh!",
                "Alle meine Entchen schwimmen in dem See, schwimmen in dem See,\n =?UTF-8?B?S8O2cGZjaGVuIGluIGRhcyBXYXNzZXIsIFNjaHfDpG56Y2hlbiBpbiBkaWUg?=\n =?UTF-8?B?SMO2aCE=?="
            ]
        ];
    }

    /**
     * base64 chunk are 4 chars long
     * try to encode/decode with 4 line length
     * @dataProvider dataTestEncodeMailHeaderBase64wrap
     */
    public function testEncodeMailHeaderBase64wrap($str)
    {
        $this->assertEquals($str, mb_decode_mimeheader(Mime::encode($str, "B", '', Mime::LINEEND, false)));
        $this->assertEquals($str, mb_decode_mimeheader(Mime::encode($str, "B", '', Mime::LINEEND, false)));
        $this->assertEquals($str, mb_decode_mimeheader(Mime::encode($str, "B", '', Mime::LINEEND, false)));
        $this->assertEquals($str, mb_decode_mimeheader(Mime::encode($str, "B", '', Mime::LINEEND, false)));
    }

    public static function dataTestEncodeMailHeaderBase64wrap()
    {
        return [
            ["äöüäöüäöüäöüäöüäöüäöü"],
            ["Alle meine Entchen schwimmen in dem See, schwimmen in dem See, "
                . "Köpfchen in das Wasser, Schwänzchen in die Höh!"]
        ];
    }

    public function testLineLengthInQuotedPrintableHeaderEncoding()
    {
        $subject = "Alle meine Entchen schwimmen in dem See, schwimmen in dem See, "
            . "Köpfchen in das Wasser, Schwänzchen in die Höh!";
        $encoded = Mime::encode($subject, 'Q', '', Mime::LINEEND, false);

        foreach (explode(Mime::LINEEND, $encoded) as $line)
            $this->assertLessThanOrEqual(76, strlen($line));
    }

    public function dataTestCharsetDetection()
    {
        return [
            ["ASCII", "test"],
            ["ASCII", "=?ASCII?Q?test?="],
            ["UTF-8", "=?UTF-8?Q?test?="],
            ["ISO-8859-1", "=?ISO-8859-1?Q?Pr=FCfung_f=FCr?= Entwerfen von einer MIME kopfzeile"],
            ["UTF-8", "=?UTF-8?Q?Pr=C3=BCfung=20Pr=C3=BCfung?="]
        ];
    }

    /**
     * @dataProvider dataTestCharsetDetection
     */
    public function testCharsetDetection($expected, $string)
    {
        $this->assertEquals($expected, Mime::mimeDetectCharset($string));
    }

    public function testEncodingRaw()
    {
        $str = "foo bar boo baz";
        $this->assertEquals($str, Mime::encode($str, Mime::ENCODING_7BIT));

        $thrown = false;
        $str = "föö bär boo baz";
        try
        {
            Mime::encode($str, Mime::ENCODING_7BIT);
        }
        catch (InvalidArgumentException $e)
        {
            $this->assertContains('Invalid characters in 7-bit encoding', $e->getMessage());
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertEquals($str, Mime::encode($str, Mime::ENCODING_8BIT));
    }

    public function testInvalidEncoding()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid encoding scheme: foo");

        Mime::encode('foo bar', 'foo');
    }

    public function testIncorrectCharset()
    {
        $utf8 = "föö bär";
        $iso88591 = mb_convert_encoding($utf8, "iso-8859-1", "utf-8");
        $utf32 = mb_convert_encoding($utf8, "utf-32le", "utf-8");

        mb_internal_encoding("UTF-8");
        $this->assertEquals('Subject: =?UTF-8?Q?f=C3=B6=C3=B6=20b=C3=A4r?=', Mime::encode($utf8, 'Q', 'Subject'));
        $this->assertFalse(Mime::encode($iso88591, 'Q', 'Subject'));
        $this->assertFalse(Mime::encode($utf32, 'Q', 'Subject'));

        mb_internal_encoding("ISO-8859-1");
        $this->assertEquals('Subject: =?ISO-8859-1?Q?f=F6=F6=20b=E4r?=', Mime::encode($iso88591, 'Q', 'Subject'));

        // ISO-8859-1 will conform to UTF-8 codes
        $this->assertEquals('Subject: =?ISO-8859-1?Q?f=C3=B6=C3=B6=20b=C3=A4r?=', Mime::encode($utf8, 'Q', 'Subject'));

        mb_internal_encoding("UTF-32LE");
        $encoded = Mime::encode($utf32, 'Q', 'Subject');
        $this->assertEquals('Subject: =?UTF-32LE?Q?f=F6=F6=20b=E4r?=', $encoded);
        $this->assertFalse(Mime::encode($utf8, 'Q', 'Subject'));
        $this->assertFalse(Mime::encode($iso88591, 'Q', 'Subject'));
    }

    public function testBoundaryStartAndEnd()
    {
        $m = new Mime;

        $boundary = $m->boundary();
        
        $boundary_start = $m->boundaryLine();
        $boundary_end = $m->mimeEnd();

        $this->assertEquals("\n--{$boundary}\n", $boundary_start);
        $this->assertEquals("\n--{$boundary}--\n", $boundary_end);
    }
}
