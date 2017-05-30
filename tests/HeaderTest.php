<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Header was adapted from Zend\Mail\Header\Headers.
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
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\Mail;

use PHPUnit\Framework\TestCase;

/**
 * @covers Wedeto\Mail\Header
 */
class HeaderTest extends TestCase
{
    public function testWrapHeaderWithOnlyAscii()
    {
        $string = str_repeat('foobarblahblahblah baz bat', 4);
        $expected = 'foobarblahblahblah baz batfoobarblahblahblah baz batfoobarblahblahblah baz' . "\r\n" .
            ' batfoobarblahblahblah baz bat';

        $test = Header::wrap($string);
        $this->assertEquals($expected, $test);
    }

    public function testWrapHeaderWithAsciiAndUnicode()
    {
        $string = str_repeat('foobarblahblahblah baz bät', 3);
        $expected = 'foobarblahblahblah baz =?UTF-8?Q?b=C3=A4tfoobarblahblahblah=20baz=20b?=' . "\r\n"
            . '  =?UTF-8?Q?=C3=A4tfoobarblahblahblah=20baz=20b=C3=A4t?=';

        $test = Header::wrap($string, true, '');
        $this->assertEquals($expected, $test);
        $this->assertEquals($string, mb_decode_mimeheader($test));
    }

    public function testMimeEncoding()
    {
        $string   = 'Umlauts: ä';
        $expected = 'Umlauts: =?UTF-8?Q?=C3=A4?=';

        $test = Header::wrap($string, 'UTF-8', '');
        $this->assertEquals($expected, $test);
        $this->assertEquals($string, iconv_mime_decode($test, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8'));
    }

    /**
     * Test that fails with Header::canBeEncoded at lowest level:
     *   iconv_mime_encode(): Unknown error (7)
     *
     * which can be triggered as:
     *   $header = new GenericHeader($name, $value);
     */
    public function testCanBeEncoded()
    {
        $value = "[#77675] New Issue:xxxxxxxxx xxxxxxx xxxxxxxx xxxxxxxxxxxxx xxxxxxxxxx xxxxxxxx, tähtaeg xx.xx, xxxx";
        $res = Header::canBeEncoded($value);
        $this->assertTrue($res);

        $value = '';
        for ($i = 0; $i < 255; ++$i)
            $value .= chr($i);

        $res = Header::canBeEncoded($value);
        if ($res)
            var_Dump(Header::wrap($value));
        $this->assertFalse($res);
    }

    public function testInvalidGetFormat()
    {
        $test = new Header;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid format');

        var_dump($test->get('Date', 'FOO'));
    }

    public function testGetInvalidAddress()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name is not an address header: Myaddress');
    
        $header = new Header;
        $header->getAddress('MyAddress');
    }

    public function testUnsettingOfValues()
    {
        $dt = "Mon, 29 May 2017 10:23:24 +0200";
        $header = new Header;
        $header->set('Date', $dt);

        $this->assertEquals('Date: ' . $dt, $header->get('Date', Header::FORMAT_ENCODED));

        $header->set('Date', null);
        $this->assertEquals(null, $header->get('Date'));
    }

    public function testSettingAddress()
    {
        $address = ['info@wedeto.net' => 'Wedeto Team'];
        $header = new Header;
        $header->set('Sender', $address);

        $address2 = $header->get('Sender');
        $this->assertTrue(is_array($address2));
        $this->assertEquals(1, count($address2));

        $first = reset($address2);
        $this->assertInstanceOf(Address::class, $first);
        $this->assertEquals('info@wedeto.net', $first->getEmail());
    }

    public function testAddInvalidAddressField()
    {
        $header = new Header;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name is not an address header');
        $header->addAddress('Myaddress', 'info@wedeto.net');
    }

    public function testAddMultipleSendersThrowsException()
    {
        $header = new Header;
        $header->addAddress('Sender', 'info@wedeto.net');

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Only one Sender can be set');
        $header->addAddress('Sender', 'info2@wedeto.net');
    }

    public function testSetGetContentType()
    {
        $header = new Header;

        $header->set('Content-type', 'image/png; resolution=450x250');
        
        $this->assertEquals([
            'type' => 'image/png',
            'parameters' => ['resolution' => '450x250']
        ], $header->getContentType(Header::FORMAT_RAW));

        $this->assertEquals(
            "Content-Type: image/png;\r\n resolution=\"450x250\"",
            $header->getContentType(Header::FORMAT_ENCODED)
        );
    }

    public function testSetDate()
    {
        $dt = new \DateTime();
        $idt = new \DateTimeImmutable();
        $cal = \IntlCalendar::fromDateTime($dt);

        $header = new Header;
        $header->setDate($dt);
        $this->assertEquals(
            'Date: ' . $dt->format('r'),
            $header->get('Date', Header::FORMAT_ENCODED)
        );

        $header->setDate($idt);
        $this->assertEquals(
            'Date: ' . $dt->format('r'),
            $header->get('Date', Header::FORMAT_ENCODED)
        );

        $header->setDate($cal);
        $this->assertEquals(
            'Date: ' . $dt->format('r'),
            $header->get('Date', Header::FORMAT_ENCODED)
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Date must be DateTime, IntlCalendar, date string or valid Unix timestamp");
        $header->setDate(new \StdClass);
    }

    public function testToString()
    {
        $dt = new \DateTime;
        $header = new Header;
        $header->setDate($dt);
        $header->set('Subject', 'foo');

        $str = $header->toString();
        $this->assertEquals($str, (string)$header);
        $this->assertEquals('Date: ' . $dt->format('r') . "\r\nSubject: foo\r\n", $str);

        // Bcc should be ignored
        $header->set('Bcc', 'wedeto@wedeto.net');
        $str = $header->toString();
        $this->assertEquals($str, (string)$header);
        $this->assertEquals('Date: ' . $dt->format('r') . "\r\nSubject: foo\r\n", $str);

        // Cc should be Added
        $header->set('Cc', 'wedeto@wedeto.net');
        $str = $header->toString();
        $this->assertEquals($str, (string)$header);
        $this->assertEquals('Date: ' . $dt->format('r') . "\r\nSubject: foo\r\nCc: wedeto@wedeto.net\r\n", $str);
    }

    public function testIteratorInterface()
    {
        $dt = new \DateTime;
        $header = new Header;
        $header->setDate($dt);
        $header->set('Subject', 'foo');
        $header->set('Bcc', 'bcc-wedeto@wedeto.net');
        $header->set('Cc', 'cc-wedeto@wedeto.net');
        $header->set('Content-Type', 'text/plain; charset=utf-8');

        $keys = [];
        $values = [];
        $cc = null;
        $bcc = null;
        foreach ($header as $k => $v)
        {
            $keys[] = $k;
            $values[] = $v;
            if ($k === 'Bcc')
                $bcc = $v;
            elseif ($k === 'Cc')
                $cc = $v;
        }

        $this->assertContains('Date', $keys);
        $this->assertContains($dt, $values);

        $this->assertContains('Cc', $keys);
        $this->assertEquals(1, count($cc));
        $first = reset($cc);
        $this->assertEquals('cc-wedeto@wedeto.net', $first->getEmail());

        $this->assertContains('Bcc', $keys);
        $this->assertEquals(1, count($bcc));
        $first = reset($bcc);
        $this->assertEquals('bcc-wedeto@wedeto.net', $first->getEmail());

        $this->assertContains('Subject', $keys);
        $this->assertContains('foo', $values);

        $this->assertContains('Content-Type', $keys);
        $this->assertContains("Content-Type: text/plain;\r\n charset=\"utf-8\"", $values);
    }
}
