<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Mime\Attachment was adapted from Zend\Mime\Part
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
use Wedeto\Mail\MailException;
use Wedeto\IO\IOException;

/**
 * @covers Wedeto\Mail\Mime\Attachment
 */
class AttachmentTest extends TestCase
{
    public function testConstructAttachment()
    {
        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);

        $part = new Attachment('bar.png', $rsrc, 'image/png');
        $this->assertEquals('image/png', $part->getType());
        $this->assertEquals(Mime::ENCODING_BASE64, $part->getEncoding());
        $this->assertEquals(Mime::DISPOSITION_ATTACHMENT, $part->getDisposition());
    }

    public function testDetectMimeFromFileName()
    {
        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);

        $part = new Attachment('bar.jpg', $rsrc);
        $this->assertEquals('image/jpeg', $part->getType());
        $this->assertEquals(Mime::ENCODING_BASE64, $part->getEncoding());
        $this->assertEquals(Mime::DISPOSITION_ATTACHMENT, $part->getDisposition());
    }

    public function testDetectMimeFromFile()
    {
        // WMV header  
        $wmv_header =  chr(0x30) . chR(0x26) . chr(0xb2) . chR(0x75) . chr(0x8e)
            . chr(0x66) . chr(0xcf) . chr(0x11) . chr(0xa6) . chr(0xd9) . chr(0x00)
            . chr(0xaa) . chr(0x00) . chr(0x62) . chr(0xce) . chr(0x6c);

        $tmp_file = tempnam(sys_get_temp_dir(), 'wedetotest');
        file_put_contents($tmp_file, $wmv_header);

        $part = new Attachment($tmp_file);
        $this->assertEquals('video/x-ms-asf', $part->getType());
        $this->assertEquals(Mime::ENCODING_BASE64, $part->getEncoding());
        $this->assertEquals(Mime::DISPOSITION_ATTACHMENT, $part->getDisposition());
    }

    public function testMimeNotDetectable()
    {
        $rsrc = fopen('php://memory', 'rw');
        fwrite($rsrc, 'foobar');
        rewind($rsrc);

        $part = new Attachment('data.dat', $rsrc);
        $this->assertEquals(Mime::TYPE_OCTETSTREAM, $part->getType());
        $this->assertEquals(Mime::ENCODING_BASE64, $part->getEncoding());
        $this->assertEquals(Mime::DISPOSITION_ATTACHMENT, $part->getDisposition());
    }

    public function testInvalidAttachment()
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'wedetotest');
        unlink($tmp_file);

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Cannot read file');
        $part = new Attachment($tmp_file);
    }
}
