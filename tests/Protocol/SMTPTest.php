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
 * @covers Wedeto\Mail\Protocol\AbstractProtocol
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

    public function testPlainLogin()
    {
        $this->connection->setOptions(['username' => 'foo', 'password' => 'loremipsum', 'auth_type' => 'PLAIN']);
        $opts = $this->connection->getOptions();
        $this->assertTrue(is_array($opts));
        $this->assertEquals('foo', $opts['username']);
        $this->assertEquals('loremipsum', $opts['password']);
        $this->assertEquals('PLAIN', $opts['auth_type']);
        
        $this->connection->auth();
        $log = $this->connection->getLog();

        $this->assertEquals('foo', $this->connection->getUsername());
        $this->assertEquals('loremipsum', $this->connection->getPassword());

        $expected = "AUTH PLAIN\r\n" . base64_encode(chr(0) . 'foo' . chr(0) . 'loremipsum') . "\r\n";
        $this->assertEquals($expected, $log);
    }

    public function testPlainLoginWithoutAuthType()
    {
        $this->connection->setOptions(['username' => 'foo', 'password' => 'loremipsum']);
        $opts = $this->connection->getOptions();
        $this->assertTrue(is_array($opts));
        $this->assertEquals('foo', $opts['username']);
        $this->assertEquals('loremipsum', $opts['password']);
        $this->assertEquals('PLAIN', $opts['auth_type']);
        
        $this->connection->auth();
        $log = $this->connection->getLog();

        $this->assertEquals('foo', $this->connection->getUsername());
        $this->assertEquals('loremipsum', $this->connection->getPassword());

        $expected = "AUTH PLAIN\r\n" . base64_encode(chr(0) . 'foo' . chr(0) . 'loremipsum') . "\r\n";
        $this->assertEquals($expected, $log);
    }

    public function testLoginLogin()
    {
        $this->connection->setOptions(['username' => 'foo', 'password' => 'loremipsum', 'auth_type' => 'LOGIN']);
        $opts = $this->connection->getOptions();
        $this->assertTrue(is_array($opts));
        $this->assertEquals('foo', $opts['username']);
        $this->assertEquals('loremipsum', $opts['password']);
        $this->assertEquals('LOGIN', $opts['auth_type']);
        
        $this->connection->auth();
        $log = $this->connection->getLog();

        $this->assertEquals('foo', $this->connection->getUsername());
        $this->assertEquals('loremipsum', $this->connection->getPassword());

        $expected = "AUTH LOGIN\r\n" . base64_encode('foo') . "\r\n" . base64_encode('loremipsum') . "\r\n";
        $this->assertEquals($expected, $log);
    }

    public function testCRAMMD5Login()
    {
        $this->connection->setOptions(['username' => 'foo', 'password' => 'loremipsum', 'auth_type' => 'CRAM-MD5']);
        $opts = $this->connection->getOptions();
        $this->assertTrue(is_array($opts));
        $this->assertEquals('foo', $opts['username']);
        $this->assertEquals('loremipsum', $opts['password']);
        $this->assertEquals('CRAM-MD5', $opts['auth_type']);
        
        $challenge = 'challenge';
        $this->connection->setExpectResponse(base64_encode($challenge));
        $this->connection->auth();
        $log = $this->connection->getLog();

        $this->assertEquals('foo', $this->connection->getUsername());
        $this->assertEquals('loremipsum', $this->connection->getPassword());

        $class = new ReflectionClass(SMTP::class);
        $method = $class->getMethod('hmacMD5');
        $method->setAccessible(true);
        $digest = $method->invokeArgs(
            $this->connection,
            ['loremipsum', $challenge]
        );

        $expected = "AUTH CRAM-MD5\r\n" . base64_encode('foo ' . $digest) . "\r\n";
        $this->assertEquals($expected, $log);
    }

    public function testReset()
    {
        $this->connection->rset();
        $log = $this->connection->getLog();

        $this->assertEquals('RSET' . "\r\n", $log);
    }

    public function testMailBeforeConnectError()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('A valid session has not been started');
        $this->connection->mail('info@wedeto.net');
    }

    public function testRcptBeforeMailError()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('No sender reverse path has been supplied');
        $this->connection->rcpt('info@wedeto.net');
    }

    public function testValidateSSLOptions()
    {
        $this->connection->setOptions(['ssl' => 'ssl', 'port' => '466']);
        $opts = $this->connection->getOptions();
        $this->assertEquals(466, $opts['port']);
        $this->assertEquals('ssl', $this->connection->getSecure());
        $this->assertEquals('ssl', $this->connection->getTransport());

        $this->connection->setOptions(['ssl' => 'ssl']);
        $opts = $this->connection->getOptions();
        $this->assertEquals(465, $opts['port']);
        $this->assertEquals('ssl', $this->connection->getSecure());
        $this->assertEquals('ssl', $this->connection->getTransport());

        $this->connection->setOptions(['ssl' => 'tls', 'port' => '466']);
        $opts = $this->connection->getOptions();
        $this->assertEquals(466, $opts['port']);
        $this->assertEquals('tls', $this->connection->getSecure());
        $this->assertEquals('tcp', $this->connection->getTransport());

        $this->connection->setOptions(['ssl' => 'tls']);
        $opts = $this->connection->getOptions();
        $this->assertEquals('tls', $this->connection->getSecure());
        $this->assertEquals('tcp', $this->connection->getTransport());
    }

    public function testInvalidHostname()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid hostname');
        $this->connection->setOptions(['host' => 3]);
    }

    public function testInvalidAuthTypeInt()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid authentication type');
        $this->connection->setOptions(['auth_type' => 3]);
    }

    public function testInvalidAuthType()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid authentication type: "FOO"');
        $this->connection->setOptions(['auth_type' => 'foo']);
    }

    public function testSetAuthTypeWithoutUsername()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Authentication requires username and password');
        $this->connection->setOptions(['auth_type' => 'login']);
    }

    public function testSetAuthTypeWithoutPassword()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Authentication requires username and password');
        $this->connection->setOptions(['auth_type' => 'login', 'username' => 'foo']);
    }

    public function testSetNonStringSSL()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid SSL type');
        $this->connection->setOptions(['ssl' => 1]);
    }

    public function testSetSSLWithInvalidString()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid SSL type: foo');
        $this->connection->setOptions(['ssl' => 'foo']);
    }

    public function testSetInvalidHELOType()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid HELO specified');
        $this->connection->setOptions(['helo' => 3]);
    }

    public function testSetInvalidHELOHost()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unresolvable HELO specified');
        $this->connection->setOptions(['helo' => 'this.host.should.hopefully.not.exist.top.level.wedeto']);
    }

    public function testSetInvalidServerHost()
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid SMTP server host');
        $this->connection->setOptions(['host' => 'this.host.should.hopefully.not.exist.top.level.wedeto']);
    }

    public function testGetHostAndPort()
    {
        $this->connection->setOptions(['host' => 'wedeto.net', 'port' => 123]);
        $this->assertEquals(123, $this->connection->getPort());
        $this->assertEquals('wedeto.net', $this->connection->getHost());
    }

    public function testSetAndGetLogSize()
    {
        $this->assertSame($this->connection, $this->connection->setMaximumLog(5));
        $this->assertEquals(5, $this->connection->getMaximumLog());

        for ($i = 0; $i < 10; ++$i)
            $this->connection->rset();

        $log = $this->connection->getLog();
        $expected = str_repeat("RSET\r\n", 5);
        $this->assertEquals($expected, $log);

        $this->assertSame($this->connection, $this->connection->setMaximumLog(7));
        $this->assertEquals(7, $this->connection->getMaximumLog());

        for ($i = 0; $i < 10; ++$i)
            $this->connection->rset();

        $log = $this->connection->getLog();
        $expected = str_repeat("RSET\r\n", 7);
        $this->assertEquals($expected, $log);
    }

    public function testGetRequestAndResponse()
    {
        $this->connection->setExpectResponse('loremipsum');
        $this->connection->rset();

        $this->assertEquals('RSET', $this->connection->getRequest());
        $this->assertEquals('loremipsum', $this->connection->getResponse());

        $log = $this->connection->getLog();
        $this->assertEquals("RSET\r\n", $log);

        $log = $this->connection->getLog();
        $this->assertEquals("RSET\r\n", $log);

        $log = $this->connection->resetLog();
        $log = $this->connection->getLog();
        $this->assertEmpty($log);
    }
}

final class ErroneousSMTP extends AbstractProtocol
{
    public function connect($customRemote = null)
    {
        return $this->_connect($customRemote);
    }
}
