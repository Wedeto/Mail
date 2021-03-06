<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Mime\Message was adapted from Zend\Mime\Message
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
 * @covers Wedeto\Mail\Mime\Message
 */
class MessageTest extends TestCase
{
    public function testMultiPart()
    {
        $msg = new Message();  // No Parts
        $this->assertFalse($msg->isMultiPart());
    }

    public function testSetGetParts()
    {
        $msg = new Message();  // No Parts
        $p = $msg->getParts();
        $this->assertInternalType('array', $p);
        $this->assertEmpty($p);

        $p2 = [];
        $p2[] = new Part('This is a test');
        $p2[] = new Part('This is another test');
        $msg->setParts($p2);
        $p = $msg->getParts();
        $this->assertInternalType('array', $p);
        $this->assertCount(2, $p);
    }

    public function testGetMime()
    {
        $msg = new Message();  // No Parts
        $m = $msg->getMime();
        $this->assertInstanceOf(Mime::class, $m);

        $msg = new Message();  // No Parts
        $mime = new Mime('1234');
        $msg->setMime($mime);
        $m2 = $msg->getMime();
        $this->assertInstanceOf(Mime::class, $m2);
        $this->assertEquals('1234', $m2->boundary());
    }

    public function testGenerate()
    {
        $msg = new Message();  // No Parts
        $p1 = new Part('This is a test');
        $p2 = new Part('This is another test');
        $msg->addPart($p1);
        $msg->addPart($p2);
        $res = $msg->generateMessage();
        $mime = $msg->getMime();
        $boundary = $mime->boundary();
        $p1 = strpos($res, $boundary);
        // $boundary must appear once for every mime part
        $this->assertNotFalse($p1);
        if ($p1) {
            $p2 = strpos($res, $boundary, $p1 + strlen($boundary));
            $this->assertNotFalse($p2);
        }
        // check if the two test messages appear:
        $this->assertContains('This is a test', $res);
        $this->assertContains('This is another test', $res);
        // ... more in ZMailTest
    }

    public function testNonMultipartMessageShouldNotRemovePartFromMessage()
    {
        $message = new Message();  // No Parts
        $part    = new Part('This is a test');
        $message->addPart($part);
        $message->generateMessage();

        $parts = $message->getParts();
        $test  = current($parts);
        $this->assertSame($part, $test);
    }

    public function testPassEmptyArrayIntoSetPartsShouldReturnEmptyString()
    {
        $mimeMessage = new Message();
        $mimeMessage->setParts([]);

        $this->assertEquals('', $mimeMessage->generateMessage());
    }

    public function testDuplicatePartAddedWillThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $message = new Message();
        $part = new Part('This is a test');
        $message->addPart($part);
        $message->addPart($part);
    }

    public function testSetAndGetType()
    {
        $message = new Message();

        $part = new Part('This is a test');
        $message->addPart($part);

        $part2 = new Part('This is another test');
        $message->addPart($part2);

        $this->assertEquals(Mime::MULTIPART_MIXED, $message->getType());
        $this->assertSame($message, $message->setType(Mime::MULTIPART_ALTERNATIVE));
        $this->assertEquals(Mime::MULTIPART_ALTERNATIVE, $message->getType());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("A mime message should be multipart/");
        $message->setType('image/png');
    }

    public function testAddAndRemovePart()
    {
        $message = new Message();
        $part = new Part('This is a test');

        $this->assertSame($message, $message->addPart($part));
        $this->assertFalse($message->isMultiPart());

        $parts = $message->getParts();
        $this->assertEquals(1, count($parts));
        $this->assertSame($part, reset($parts));

        $this->assertTrue($message->removePart($part), "Removing part for the first time should return true");
        $this->assertFalse($message->removePart($part), "Removing part for the second time should return false");
    }

    public function testGetHeadersArray()
    {
        $message = new Message();
        $part = new Part('This is a test');
        $part->setDisposition(Mime::DISPOSITION_INLINE);
        $part->setType(Mime::TYPE_TEXT);

        $this->assertSame($message, $message->addPart($part));

        $this->assertEquals(1, count($message->getParts()));
        $header = $message->getPartHeadersArray(0);

        $this->assertEquals(3, count($header));
        $this->assertContains(['Content-Type', 'text/plain'], $header);
        $this->assertContains(['Content-Transfer-Encoding', '8bit'], $header);
        $this->assertContains(['Content-Disposition', 'inline'], $header);
    }

    public function testPartInterface()
    {
        $message = new Message();
        $part = new Part('This is a test');
        $part->setDisposition(Mime::DISPOSITION_INLINE);
        $part->setType(Mime::TYPE_TEXT);

        $this->assertSame($message, $message->addPart($part));
        $this->assertEquals(1, count($message->getParts()));

        $content = $message->getContent();
        $this->assertEquals('This is a test', $content);

        $mime = $message->getMime();

        $header = $message->getHeadersArray();
        $this->assertInstanceOf(Mime::class, $mime);
        $this->assertTrue(is_array($header));
        $this->assertEquals(1, count($header));
        $this->assertContains(['Content-Type', "multipart/mixed;\n boundary=\"" . $mime->boundary() . "\""], $header);

        $header = $message->getHeadersArray("\r\n");
        $this->assertInstanceOf(Mime::class, $mime);
        $this->assertTrue(is_array($header));
        $this->assertEquals(1, count($header));
        $this->assertContains(['Content-Type', "multipart/mixed;\r\n boundary=\"" . $mime->boundary() . "\""], $header);

        $header = $message->getHeaders();
        $expected = "Content-Type: multipart/mixed;\n boundary=\"" . $mime->boundary() . "\"\n";
        $this->assertEquals($expected, $header);

        $header = $message->getHeaders("\r\n");
        $expected = "Content-Type: multipart/mixed;\r\n boundary=\"" . $mime->boundary() . "\"\r\n";
        $this->assertEquals($expected, $header);
    }
}
