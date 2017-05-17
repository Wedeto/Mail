<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Mime\Part was adapted from Zend\Mime\Part
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

/**
 * @covers Wedeto\Mail\Mime\Part
 */
class PartTest extends TestCase
{
    /** MIME part test object */
    protected $part = null;

    /** MIME part test text */
    protected $testText;

    protected function setUp()
    {
        $this->testText = 'safdsafsa�lg ��gd�� sd�jg�sdjg�ld�gksd�gj�sdfg�dsj'
            .'�gjsd�gj�dfsjg�dsfj�djs�g kjhdkj fgaskjfdh gksjhgjkdh gjhfsdghdhgksdjhg';
        $this->part = new Mime\Part($this->testText);
        $this->part->encoding = Mime\Mime::ENCODING_BASE64;
        $this->part->type = "text/plain";
        $this->part->filename = 'test.txt';
        $this->part->disposition = 'attachment';
        $this->part->charset = 'iso8859-1';
        $this->part->id = '4711';
    }

    public function testHeaders()
    {
        $expectedHeaders = ['Content-Type: text/plain',
                                 'Content-Transfer-Encoding: ' . Mime\Mime::ENCODING_BASE64,
                                 'Content-Disposition: attachment',
                                 'filename="test.txt"',
                                 'charset=iso8859-1',
                                 'Content-ID: <4711>'];

        $actual = $this->part->getHeaders();

        foreach ($expectedHeaders as $expected) {
            $this->assertContains($expected, $actual);
        }
    }

    public function testContentEncoding()
    {
        // Test with base64 encoding
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, base64_decode($content));
        // Test with quotedPrintable Encoding:
        $this->part->encoding = Mime\Mime::ENCODING_QUOTEDPRINTABLE;
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, quoted_printable_decode($content));
        // Test with 8Bit encoding
        $this->part->encoding = Mime\Mime::ENCODING_8BIT;
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, $content);
    }

    public function testStreamEncoding()
    {
        $testfile = realpath(__FILE__);
        $original = file_get_contents($testfile);

        // Test Base64
        $fp = fopen($testfile, 'rb');
        $this->assertInternalType('resource', $fp);
        $part = new Mime\Part($fp);
        $part->encoding = Mime\Mime::ENCODING_BASE64;
        $fp2 = $part->getEncodedStream();
        $this->assertInternalType('resource', $fp2);
        $encoded = stream_get_contents($fp2);
        fclose($fp);
        $this->assertEquals(base64_decode($encoded), $original);

        // test QuotedPrintable
        $fp = fopen($testfile, 'rb');
        $this->assertInternalType('resource', $fp);
        $part = new Mime\Part($fp);
        $part->encoding = Mime\Mime::ENCODING_QUOTEDPRINTABLE;
        $fp2 = $part->getEncodedStream();
        $this->assertInternalType('resource', $fp2);
        $encoded = stream_get_contents($fp2);
        fclose($fp);
        $this->assertEquals(quoted_printable_decode($encoded), $original);
    }

    public function testGetRawContentFromPart()
    {
        $this->assertEquals($this->testText, $this->part->getRawContent());
    }

    public function testContentEncodingWithStreamReadTwiceINaRow()
    {
        $testfile = realpath(__FILE__);
        $original = file_get_contents($testfile);

        $fp = fopen($testfile, 'rb');
        $part = new Mime\Part($fp);
        $part->encoding = Mime\Mime::ENCODING_BASE64;
        $contentEncodedFirstTime  = $part->getContent();
        $contentEncodedSecondTime = $part->getContent();
        $this->assertEquals($contentEncodedFirstTime, $contentEncodedSecondTime);
        fclose($fp);

        $fp = fopen($testfile, 'rb');
        $part = new Mime\Part($fp);
        $part->encoding = Mime\Mime::ENCODING_QUOTEDPRINTABLE;
        $contentEncodedFirstTime  = $part->getContent();
        $contentEncodedSecondTime = $part->getContent();
        $this->assertEquals($contentEncodedFirstTime, $contentEncodedSecondTime);
        fclose($fp);
    }

    public function testSettersGetters()
    {
        $part = new Mime\Part();
        $part->setContent($this->testText)
             ->setEncoding(Mime\Mime::ENCODING_8BIT)
             ->setType('text/plain')
             ->setFilename('test.txt')
             ->setDisposition('attachment')
             ->setCharset('iso8859-1')
             ->setId('4711')
             ->setBoundary('frontier')
             ->setLocation('fiction1/fiction2')
             ->setLanguage('en')
             ->setIsStream(false)
             ->setFilters(['foo'])
             ->setDescription('foobar');

        $this->assertEquals($this->testText, $part->getContent());
        $this->assertEquals(Mime\Mime::ENCODING_8BIT, $part->getEncoding());
        $this->assertEquals('text/plain', $part->getType());
        $this->assertEquals('test.txt', $part->getFileName());
        $this->assertEquals('attachment', $part->getDisposition());
        $this->assertEquals('iso8859-1', $part->getCharset());
        $this->assertEquals('4711', $part->getId());
        $this->assertEquals('frontier', $part->getBoundary());
        $this->assertEquals('fiction1/fiction2', $part->getLocation());
        $this->assertEquals('en', $part->getLanguage());
        $this->assertEquals(false, $part->isStream());
        $this->assertEquals(['foo'], $part->getFilters());
        $this->assertEquals('foobar', $part->getDescription());
    }

    public function invalidContentTypes()
    {
        return [
            'null'       => [null],
            'false'      => [false],
            'true'       => [true],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [['string']],
            'object'     => [(object) ['content' => 'string']],
        ];
    }

    /**
     * @dataProvider invalidContentTypes
     */
    public function testConstructorRaisesInvalidArgumentExceptionForInvalidContentTypes($content)
    {
        $this->setExpectedException(Mime\Exception\InvalidArgumentException::class);
        new Mime\Part($content);
    }

    /**
     * @dataProvider invalidContentTypes
     */
    public function testSetContentRaisesInvalidArgumentExceptionForInvalidContentTypes($content)
    {
        $part = new Mime\Part();
        $this->setExpectedException(Mime\Exception\InvalidArgumentException::class);
        $part->setContent($content);
    }
}
