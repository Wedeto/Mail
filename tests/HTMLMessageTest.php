<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Message was adapted from Zend\Mail\Message
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
 * @covers Wedeto\Mail\HTMLMessage
 */
class HTMLMessageTest extends TestCase
{
    public function testBasicHTMLMessage()
    {
        $message = new HTMLMessage;

        $body = $message->getBody();
        $this->assertInstanceOf(Mime\Message::class, $body);
        $this->assertContains(Mime\Mime::MULTIPART_ALTERNATIVE, $message->getHeader()->getContentType(Header::FORMAT_ENCODED));

        $message->setHTML('<h1>Head</h1><p>Foo</p>');

        $boundary = $body->getMime()->boundary();

        $expected = <<<MSG
This is a message in Mime Format. If you see this, your mail reader does not support this format.

--$boundary
Content-Type: text/plain
Content-Transfer-Encoding: 8bit

HeadFoo

--$boundary
Content-Type: text/html
Content-Transfer-Encoding: 8bit

<h1>Head</h1><p>Foo</p>
--$boundary--
MSG;
        $bodytext = str_replace("\r\n", "\n", $message->getBodyText());
        $this->assertEquals($expected, $bodytext);
    }

    public function testBasicPlainMessage()
    {
        $message = new HTMLMessage;

        $body = $message->getBody();
        $this->assertInstanceOf(Mime\Message::class, $body);
        $this->assertContains(Mime\Mime::MULTIPART_ALTERNATIVE, $message->getHeader()->getContentType(Header::FORMAT_ENCODED));

        $message->setPlain('head foo');

        $boundary = $body->getMime()->boundary();

        $expected = <<<MSG
This is a message in Mime Format. If you see this, your mail reader does not support this format.

--$boundary
Content-Type: text/plain
Content-Transfer-Encoding: 8bit

head foo
--$boundary
Content-Type: text/html
Content-Transfer-Encoding: 8bit


--$boundary--
MSG;
        $bodytext = str_replace("\r\n", "\n", $message->getBodyText());
        $this->assertEquals($expected, $bodytext);
    }

    public function testEmptyMessageThrowsException()
    {
        $message = new HTMLMessage;

        $this->expectException(MailException::class);
        $this->expectExceptionMessage("Not forming empty e-mail message");
        $message->getBodyText();
    }

    public function testHTMLMessageWithAttachment()
    {
        $message = new HTMLMessage;
        $message->setHTML('<h1>Head</h1><p>Foo</p>');

        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);
        
        $this->assertInstanceOf(Mime\Attachment::class, $message->attach('foobar.pdf', $rsrc));

        $body = $message->getBody();
        $this->assertInstanceOf(Mime\Message::class, $body);
        $this->assertEquals(Mime\Mime::MULTIPART_MIXED, $body->getType());

        $parts = $body->getParts();
        $this->assertEquals(2, count($parts));
        $body_part = $parts[0];
        $attachment_part = $parts[1];
        $this->assertEquals('application/pdf', $attachment_part->getType());
        $this->assertInstanceOf(Mime\Message::class, $body_part);
        $this->assertEquals(Mime\Mime::MULTIPART_ALTERNATIVE, $body_part->getType());
        $this->assertInstanceOf(Mime\Attachment::class, $attachment_part);

        $parts = $body_part->getParts();
        $this->assertEquals(2, count($parts));
        $plain_part = $parts[0];
        $this->assertEquals('text/plain', $plain_part->getType());
        $html_part = $parts[1];
        $this->assertEquals('text/html', $html_part->getType());

        $message->getBodyText();

        $this->assertEquals("HeadFoo\n", $plain_part->getContent());
        $this->assertEquals('<h1>Head</h1><p>Foo</p>', $html_part->getContent());
        $this->assertEquals('foobar.pdf', $attachment_part->getFileName());
    }

    public function testHTMLMessageWithEmbeddedImage()
    {
        $message = new HTMLMessage;
        $message->setHTML('<h1>Head</h1><p>Foo</p>');

        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);
        
        $cid = $message->embed('foobar.png', $rsrc);
        $this->assertEquals('cid:', substr($cid, 0, 4));

        $body = $message->getBody();
        $this->assertInstanceOf(Mime\Message::class, $body);
        $this->assertEquals(Mime\Mime::MULTIPART_ALTERNATIVE, $body->getType());

        $parts = $body->getParts();
        $this->assertEquals(2, count($parts));
        $plain_part = $parts[0];
        $this->assertEquals('text/plain', $plain_part->getType());
        $html_wrapper = $parts[1];
        $this->assertInstanceOf(Mime\Part::class, $plain_part);
        $this->assertInstanceOf(Mime\Message::class, $html_wrapper);
        $this->assertEquals(Mime\Mime::MULTIPART_RELATED, $html_wrapper->getType());

        $parts = $html_wrapper->getParts();
        $this->assertEquals(2, count($parts));

        $html_part = $parts[0];
        $this->assertEquals('text/html', $html_part->getType());
        $embed_part = $parts[1];
        $this->assertEquals('image/png', $embed_part->getType());
        $this->assertInstanceOf(Mime\Part::class, $html_part);
        $this->assertInstanceOf(Mime\Attachment::class, $embed_part);

        $message->getBodyText();

        $this->assertEquals("HeadFoo\n", $plain_part->getContent());
        $this->assertEquals('<h1>Head</h1><p>Foo</p>', $html_part->getContent());
        $this->assertEquals('foobar.png', $embed_part->getFileName());
    }

    public function testHTMLMessageWithEmbeddedImageAndAttachment()
    {
        $message = new HTMLMessage;
        $message->setHTML('<h1>Head</h1><p>Foo</p>');

        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);

        $rsrc2 = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);
        
        $cid = $message->embed('foobar.png', $rsrc);
        $this->assertEquals('cid:' , substr($cid, 0, 4));
        $this->assertInstanceOf(Mime\Attachment::class, $message->attach('foobar.pdf', $rsrc2));

        $body = $message->getBody();
        $this->assertInstanceOf(Mime\Message::class, $body);
        $this->assertEquals(Mime\Mime::MULTIPART_MIXED, $body->getType());

        $parts = $body->getParts();
        $this->assertEquals(2, count($parts));
        $body_part = $parts[0];
        $attachment_part = $parts[1];
        $this->assertInstanceOf(Mime\Message::class, $body_part);
        $this->assertEquals(Mime\Mime::MULTIPART_ALTERNATIVE, $body_part->getType());
        $this->assertInstanceOf(Mime\Attachment::class, $attachment_part);

        $parts = $body_part->getParts();
        $this->assertEquals(2, count($parts));

        $plain_part = $parts[0];
        $html_wrapper = $parts[1];
        $this->assertInstanceOf(Mime\Part::class, $plain_part);
        $this->assertInstanceOf(Mime\Message::class, $html_wrapper);
        $this->assertEquals(Mime\Mime::MULTIPART_RELATED, $html_wrapper->getType());

        $parts = $html_wrapper->getParts();
        $this->assertEquals(2, count($parts));
        
        $html_part = $parts[0];
        $this->assertEquals('text/html', $html_part->getType());
        $embed_part = $parts[1];
        $this->assertEquals('image/png', $embed_part->getType());

        $message->getBodyText();

        $this->assertEquals("HeadFoo\n", $plain_part->getContent());
        $this->assertEquals('<h1>Head</h1><p>Foo</p>', $html_part->getContent());
        $this->assertEquals('foobar.png', $embed_part->getFileName());
        $this->assertEquals('foobar.pdf', $attachment_part->getFileName());
    }
}
