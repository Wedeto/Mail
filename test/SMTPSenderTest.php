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

require_once __DIR__ . '/Protocol/SMTPProtocolSpy.php';

/**
 * @covers Wedeto\Mail\SMTPSender
 */
class SMTPSenderTest extends TestCase
{
    /** @var Smtp */
    public $transport;
    /** @var SmtpProtocolSpy */
    public $connection;

    public function setUp()
    {
        $this->transport  = new Smtp();
        $this->connection = new SmtpProtocolSpy();
        $this->transport->setConnection($this->connection);
    }

    public function getMessage()
    {
        $message = new Message();
        $message->addTo('zf-devteam@zend.com', 'ZF DevTeam');
        $message->addCc('matthew@zend.com');
        $message->addBcc('zf-crteam@lists.zend.com', 'CR-Team, ZF Project');
        $message->addFrom([
            'zf-devteam@zend.com',
            'matthew@zend.com' => 'Matthew',
        ]);
        $message->setSender('ralph.schindler@zend.com', 'Ralph Schindler');
        $message->setSubject('Testing Zend\Mail\Transport\Sendmail');
        $message->setBody('This is only a test.');

        $message->getHeaders()->addHeaders([
            'X-Foo-Bar' => 'Matthew',
        ]);

        return $message;
    }

    /**
     *  Per RFC 2822 3.6
     */
    public function testSendMailWithoutMinimalHeaders()
    {
        $this->setExpectedException(
            'Zend\Mail\Transport\Exception\RuntimeException',
            'transport expects either a Sender or at least one From address in the Message; none provided'
        );
        $message = new Message();
        $this->transport->send($message);
    }

    /**
     *  Per RFC 2821 3.3 (page 18)
     *  - RCPT (recipient) must be called before DATA (headers or body)
     */
    public function testSendMailWithoutRecipient()
    {
        $this->setExpectedException(
            'Zend\Mail\Transport\Exception\RuntimeException',
            'at least one recipient if the message has at least one header or body'
        );
        $message = new Message();
        $message->setSender('ralph.schindler@zend.com', 'Ralph Schindler');
        $this->transport->send($message);
    }

    public function testSendMailWithEnvelopeFrom()
    {
        $message = $this->getMessage();
        $envelope = new Envelope([
            'from' => 'mailer@lists.zend.com',
        ]);
        $this->transport->setEnvelope($envelope);
        $this->transport->send($message);

        $data = $this->connection->getLog();
        $this->assertContains('MAIL FROM:<mailer@lists.zend.com>', $data);
        $this->assertContains('RCPT TO:<matthew@zend.com>', $data);
        $this->assertContains('RCPT TO:<zf-crteam@lists.zend.com>', $data);
        $this->assertContains("From: zf-devteam@zend.com,\r\n Matthew <matthew@zend.com>\r\n", $data);
    }

    public function testSendMailWithEnvelopeTo()
    {
        $message = $this->getMessage();
        $envelope = new Envelope([
            'to' => 'users@lists.zend.com',
        ]);
        $this->transport->setEnvelope($envelope);
        $this->transport->send($message);

        $data = $this->connection->getLog();
        $this->assertContains('MAIL FROM:<ralph.schindler@zend.com>', $data);
        $this->assertContains('RCPT TO:<users@lists.zend.com>', $data);
        $this->assertContains('To: ZF DevTeam <zf-devteam@zend.com>', $data);
    }

    public function testSendMailWithEnvelope()
    {
        $message = $this->getMessage();
        $to = ['users@lists.zend.com', 'dev@lists.zend.com'];
        $envelope = new Envelope([
            'from' => 'mailer@lists.zend.com',
            'to' => $to,
        ]);
        $this->transport->setEnvelope($envelope);
        $this->transport->send($message);

        $this->assertEquals($to, $this->connection->getRecipients());

        $data = $this->connection->getLog();
        $this->assertContains('MAIL FROM:<mailer@lists.zend.com>', $data);
        $this->assertContains('RCPT TO:<users@lists.zend.com>', $data);
        $this->assertContains('RCPT TO:<dev@lists.zend.com>', $data);
    }

    public function testSendMinimalMail()
    {
        $headers = new Headers();
        $headers->addHeaderLine('Date', 'Sun, 10 Jun 2012 20:07:24 +0200');

        $message = new Message();
        $message->setHeaders($headers);
        $message->setSender('ralph.schindler@zend.com', 'Ralph Schindler');
        $message->setBody('testSendMailWithoutMinimalHeaders');
        $message->addTo('zf-devteam@zend.com', 'ZF DevTeam');

        $expectedMessage = "Date: Sun, 10 Jun 2012 20:07:24 +0200\r\n"
            . "Sender: Ralph Schindler <ralph.schindler@zend.com>\r\n"
            . "To: ZF DevTeam <zf-devteam@zend.com>\r\n"
            . "\r\n"
            . "testSendMailWithoutMinimalHeaders";

        $this->transport->send($message);

        $this->assertContains($expectedMessage, $this->connection->getLog());
    }

    public function testSendMinimalMailWithoutSender()
    {
        $headers = new Headers();
        $headers->addHeaderLine('Date', 'Sun, 10 Jun 2012 20:07:24 +0200');

        $message = new Message();
        $message->setHeaders($headers);
        $message->setFrom('ralph.schindler@zend.com', 'Ralph Schindler');
        $message->setBody('testSendMinimalMailWithoutSender');
        $message->addTo('zf-devteam@zend.com', 'ZF DevTeam');

        $expectedMessage = "Date: Sun, 10 Jun 2012 20:07:24 +0200\r\n"
            . "From: Ralph Schindler <ralph.schindler@zend.com>\r\n"
            . "To: ZF DevTeam <zf-devteam@zend.com>\r\n"
            . "\r\n"
            . "testSendMinimalMailWithoutSender";

        $this->transport->send($message);

        $this->assertContains($expectedMessage, $this->connection->getLog());
    }

    public function testReceivesMailArtifacts()
    {
        $message = $this->getMessage();
        $this->transport->send($message);

        $expectedRecipients = ['zf-devteam@zend.com', 'matthew@zend.com', 'zf-crteam@lists.zend.com'];
        $this->assertEquals($expectedRecipients, $this->connection->getRecipients());

        $data = $this->connection->getLog();
        $this->assertContains('MAIL FROM:<ralph.schindler@zend.com>', $data);
        $this->assertContains('To: ZF DevTeam <zf-devteam@zend.com>', $data);
        $this->assertContains('Subject: Testing Zend\Mail\Transport\Sendmail', $data);
        $this->assertContains("Cc: matthew@zend.com\r\n", $data);
        $this->assertNotContains("Bcc: \"CR-Team, ZF Project\" <zf-crteam@lists.zend.com>\r\n", $data);
        $this->assertContains("From: zf-devteam@zend.com,\r\n Matthew <matthew@zend.com>\r\n", $data);
        $this->assertContains("X-Foo-Bar: Matthew\r\n", $data);
        $this->assertContains("Sender: Ralph Schindler <ralph.schindler@zend.com>\r\n", $data);
        $this->assertContains("\r\n\r\nThis is only a test.", $data, $data);
    }

    public function testCanUseAuthenticationExtensionsViaPluginManager()
    {
        $options    = new SmtpOptions([
            'connection_class' => 'login',
        ]);
        $transport  = new Smtp($options);
        $connection = $transport->plugin($options->getConnectionClass(), [
            'username' => 'matthew',
            'password' => 'password',
            'host'     => 'localhost',
        ]);
        $this->assertInstanceOf('Zend\Mail\Protocol\Smtp\Auth\Login', $connection);
        $this->assertEquals('matthew', $connection->getUsername());
        $this->assertEquals('password', $connection->getPassword());
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
