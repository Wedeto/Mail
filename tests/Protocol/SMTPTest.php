<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Protocol\SMTP was adapted from Zend\Mail\Protocol\Smtp.
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

namespace Wedeto\Mail\Protocol;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

use Wedeto\Mail\Message;
use Wedeto\Mail\SMTPSender;

require_once __DIR__ . '/SMTPProtocolSpy.php';

/**
 * @covers Wedeto\Mail\Protocol\SMTP
 */
class SMTPTest extends TestCase
{
    public $transport;
    public $connection;

    public function setUp()
    {
        $this->transport = new SMTPSender();
        $this->connection = new SMTPProtocolSpy();
        $this->transport->setConnection($this->connection);
    }

    public function testSendMinimalMail()
    {
        $message = new Message();
        $message
            ->addHeader('Date', 'Mon, 29 May 2017 10:23:24 +0200')
            ->setSender('foo@bar.com', 'Foo Bar')
            ->setBody('testSendMailWithoutMinimalHeaders')
            ->addTo('wedeto@wedeto.net', 'Wedeto DevTeam')
        ;
        $expectedMessage = "EHLO localhost\r\n"
                           . "MAIL FROM:<foo@bar.com>\r\n"
                           . "RCPT TO:<wedeto@wedeto.net>\r\n"
                           . "DATA\r\n"
                           . "Date: Mon, 29 May 2017 10:23:24 +0200\r\n"
                           . "Sender: Foo Bar <foo@bar.com>\r\n"
                           . "To: Wedeto DevTeam <wedeto@wedeto.net>\r\n"
                           . "\r\n"
                           . "testSendMailWithoutMinimalHeaders\r\n"
                           . ".\r\n";

        $this->transport->send($message);

        $this->assertEquals($expectedMessage, $this->connection->getLog());
    }

    public function testSendEscapedEmail()
    {
        $message = new Message();
        $message
            ->addHeader('Date', 'Mon, 29 May 2017 10:23:24 +0200')
            ->setSender('foo@bar.com', 'Foo Bar')
            ->setBody("This is a test\n.")
            ->addTo('wedeto@wedeto.net', 'Wedeto DevTeam')
        ;
        $expectedMessage = "EHLO localhost\r\n"
            . "MAIL FROM:<foo@bar.com>\r\n"
            . "RCPT TO:<wedeto@wedeto.net>\r\n"
            . "DATA\r\n"
            . "Date: Mon, 29 May 2017 10:23:24 +0200\r\n"
            . "Sender: Foo Bar <foo@bar.com>\r\n"
            . "To: Wedeto DevTeam <wedeto@wedeto.net>\r\n"
            . "\r\n"
            . "This is a test\r\n"
            . "..\r\n"
            . ".\r\n";

        $this->transport->send($message);

        $this->assertEquals($expectedMessage, $this->connection->getLog());
    }

    public function testDisconnectCallsQuit()
    {
        $this->connection->disconnect();
        $this->assertTrue($this->connection->calledQuit);
    }

    public function testDisconnectResetsAuthFlag()
    {
        $this->connection->connect();
        $this->connection->setSessionStatus(true);
        $this->connection->setAuth(true);
        $this->assertTrue($this->connection->getAuth());
        $this->connection->disconnect();
        $this->assertFalse($this->connection->getAuth());
    }

    public function testConnectHasVerboseErrors()
    {
        $smtp = new ErroneousSMTP();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonexistentremote');

        $smtp->connect('nonexistentremote');
    }

    public function testHmacMd5ReturnsExpectedHash()
    {
        $class = new ReflectionClass(SMTP::class);
        $method = $class->getMethod('hmacMD5');
        $method->setAccessible(true);

        $result = $method->invokeArgs(
            $this->connection,
            ['frodo', 'speakfriendandenter']
        );

        $this->assertEquals('be56fa81a5671e0c62e00134180aae2c', $result);
    }
}

final class ErroneousSMTP extends AbstractProtocol
{
    public function connect($customRemote = null)
    {
        return $this->_connect($customRemote);
    }
}
