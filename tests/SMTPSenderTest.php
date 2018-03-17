<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\SMTPSender was adapted from Zend\Mail\Transport\Smtp
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
use Wedeto\Mail\Protocol\SMTPProtocolSpy;
use Wedeto\Util\Configuration;

require_once __DIR__ . '/Protocol/SMTPProtocolSpy.php';

/**
 * @covers Wedeto\Mail\SMTPSender
 */
class SMTPSenderTest extends TestCase
{
    public $transport;
    public $connection;

    public function setUp()
    {
        $config = new Configuration();
        $this->config = new MailConfiguration($config);
        $this->transport = new SMTPSender($this->config);
        $this->connection = new SMTPProtocolSpy($this->config);
        $this->transport->setConnection($this->connection);
    }

    public function getMessage()
    {
        $message = new Message();
        $message->addTo('wedeto@wedeto.net', 'Wedeto DevTeam');
        $message->addCc('johndoe@wedeto.net');
        $message->addBcc('somepeople@lists.wedeto.net', 'Some Team, Wedeto Project');
        $message->addFrom([
            'wedeto@wedeto.net',
            'johndoe@wedeto.net' => 'John Doe',
        ]);
        $message->setSender('foobar@wedeto.net', 'Foo Bar');
        $message->setSubject('Testing Zend\Mail\Transport\Sendmail');
        $message->setBody('This is only a test.');

        $message->getHeader()->set('X-Foo-Bar', 'John Doe');

        return $message;
    }

    /**
     *  Per RFC 2822 3.6
     */
    public function testSendMailWithoutMinimalHeaders()
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('No sender specified');

        $message = new Message();
        $this->transport->send($message);
    }

    /**
     *  Per RFC 2821 3.3 (page 18)
     *  - RCPT (recipient) must be called before DATA (headers or body)
     */
    public function testSendMailWithoutRecipient()
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Message must have at least one recipient');

        $message = new Message();
        $message->setSender('foo@bar.com', 'Foo Bar');
        $this->transport->send($message);
    }

    public function testSendMinimalMail()
    {
        $message = new Message();
        $message->addHeader('Date', 'Mon, 29 May 2017 10:23:24 +0200');
        $message->setSender('foobar@wedeto.net', 'Foo Bar');
        $message->setBody('testSendMailWithoutMinimalHeaders');
        $message->addTo('wedeto@wedeto.net', 'Wedeto DevTeam');

        $expectedMessage = "Date: Mon, 29 May 2017 10:23:24 +0200\r\n"
            . "Sender: Foo Bar <foobar@wedeto.net>\r\n"
            . "To: Wedeto DevTeam <wedeto@wedeto.net>\r\n"
            . "\r\n"
            . "testSendMailWithoutMinimalHeaders";

        $this->transport->send($message);

        $this->assertContains($expectedMessage, $this->connection->getLog());
    }

    public function testSendMinimalMailWithoutSender()
    {
        $message = new Message();
        $message->addHeader('Date', 'Mon, 29 May 2017 10:23:24 +0200');
        $message->setFrom('foobar@wedeto.net', 'Foo Bar');
        $message->setBody('testSendMinimalMailWithoutSender');
        $message->addTo('wedeto@wedeto.net', 'Wedeto DevTeam');

        $expectedMessage = "Date: Mon, 29 May 2017 10:23:24 +0200\r\n"
            . "From: Foo Bar <foobar@wedeto.net>\r\n"
            . "To: Wedeto DevTeam <wedeto@wedeto.net>\r\n"
            . "\r\n"
            . "testSendMinimalMailWithoutSender";

        $this->transport->send($message);

        $this->assertContains($expectedMessage, $this->connection->getLog());
    }

    public function testReceivesMailArtifacts()
    {
        $message = $this->getMessage();
        $this->transport->send($message);

        $expectedRecipients = ['wedeto@wedeto.net', 'johndoe@wedeto.net', 'somepeople@lists.wedeto.net'];
        $this->assertEquals($expectedRecipients, $this->connection->getRecipients());

        $data = $this->connection->getLog();
        $this->assertContains('MAIL FROM:<foobar@wedeto.net>', $data);
        $this->assertContains('To: Wedeto DevTeam <wedeto@wedeto.net>', $data);
        $this->assertContains('Subject: Testing Zend\Mail\Transport\Sendmail', $data);
        $this->assertContains("Cc: johndoe@wedeto.net\r\n", $data);
        $this->assertContains("RCPT TO:<somepeople@lists.wedeto.net>\r\n", $data);
        $this->assertNotContains("Bcc: \"Some Team, Wedeto Project\" <somepeople@lists.wedeto.net>\r\n", $data);
        $this->assertContains("From: wedeto@wedeto.net,\r\n John Doe <johndoe@wedeto.net>\r\n", $data);
        $this->assertContains("X-Foo-Bar: John Doe\r\n", $data);
        $this->assertContains("Sender: Foo Bar <foobar@wedeto.net>\r\n", $data);
        $this->assertContains("\r\n\r\nThis is only a test.", $data, $data);
    }

    public function testReusingConnectionIssuesRset()
    {
        $message = $this->getMessage();
        $this->transport->send($message);
        $log = $this->connection->getLog();

        $message = $this->getMessage();
        $this->transport->send($message);
        $log2 = $this->connection->getLog();

        $expected = $log . "RSET" . str_replace("EHLO localhost", "", $log);
        $this->assertEquals($expected, $log2);
    }

    public function testSetAutoDisconnect()
    {
        $this->transport->setAutoDisconnect(false);
        $this->assertFalse($this->transport->getAutoDisconnect());
    }

    public function testGetDefaultAutoDisconnectValue()
    {
        $this->assertTrue($this->transport->getAutoDisconnect());
    }

    public function testAutoDisconnectTrue()
    {
        $this->connection->connect();
        unset($this->transport);
        $this->assertFalse($this->connection->hasSession());
    }

    public function testAutoDisconnectFalse()
    {
        $this->connection->connect();
        $this->transport->setAutoDisconnect(false);
        unset($this->transport);
        $this->assertTrue($this->connection->isConnected());
    }

    public function testDisconnect()
    {
        $this->connection->connect();
        $this->assertTrue($this->connection->isConnected());
        $this->transport->disconnect();
        $this->assertFalse($this->connection->isConnected());
    }

    public function testDisconnectSendReconnects()
    {
        $this->assertFalse($this->connection->hasSession());
        $this->transport->send($this->getMessage());
        $this->assertTrue($this->connection->hasSession());
        $this->connection->disconnect();

        $this->assertFalse($this->connection->hasSession());
        $this->transport->send($this->getMessage());
        $this->assertTrue($this->connection->hasSession());
    }
}
