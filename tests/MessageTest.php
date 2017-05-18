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
use stdClass;

/**
 * @covers Wedeto\Mail\Message
 */
class MessageTest extends TestCase
{
    /** @var Message */
    public $message;

    public function setUp()
    {
        $this->message = new Message();
    }

    public function testInvalidByDefault()
    {
        $this->assertFalse($this->message->isValid());
    }

    public function testSetsOrigDateHeaderByDefault()
    {
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
        $this->assertTrue(isset($headers['Date']));
        $header = $headers['Date'];
        $date = date('r');
        $date = substr($date, 0, 16);
        $test = $header;
        $test = substr($test, 0, 16);
        $this->assertEquals($date, $test);
    }

    public function testAddingFromAddressMarksAsValid()
    {
        $this->message->addFrom('zf-devteam@example.com');
        $this->assertTrue($this->message->isValid());
    }

    public function testHeadersMethodReturnsHeadersArray()
    {
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
    }

    public function testToMethodReturnsAddressListObject()
    {
        $this->message->addTo('zf-devteam@example.com');
        $to = $this->message->getTo();
        $this->assertTrue(is_array($to));
        $this->assertEquals(1, count($to));
        $first = reset($to);
        $this->assertInstanceOf(Address::class, $first);
    }

    public function testFromMethodReturnsAddressListObject()
    {
        $this->message->addFrom('zf-devteam@example.com');
        $from = $this->message->getFrom();
        $this->assertTrue(is_array($from));
    }

    public function testFromAddressListLivesInHeaders()
    {
        $this->message->addFrom('zf-devteam@example.com');
        $from = $this->message->getFrom();
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
        $this->assertTrue(isset($headers['From']));
        $header = $headers['From'];
        $this->assertSame($header, $from);
    }

    public function testCCMethodReturnsAddressListObject()
    {
        $this->message->addCC('zf-devteam@example.com');
        $cc = $this->message->getCC();
        $this->assertTrue(is_array($cc));
    }

    public function testCCAddressListLivesInHeaders()
    {
        $this->message->addCC('zf-devteam@example.com');
        $cc = $this->message->getCC();
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
        $this->assertTrue(isset($headers['Cc']));
        $header  = $headers['Cc'];
        $this->assertSame($header, $cc);
    }

    public function testBCCMethodReturnsAddressListObject()
    {
        $this->message->addBCC('zf-devteam@example.com');
        $bcc = $this->message->getBCC();
        $this->assertTrue(is_array($bcc));
    }

    public function testBCCAddressListLivesInHeaders()
    {
        $this->message->addBCC('zf-devteam@example.com');
        $bcc = $this->message->getBCC();
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
        $this->assertTrue(isset($headers['Bcc']));
        $header = $headers['Bcc'];
        $this->assertSame($header, $bcc);
    }

    public function testReplyToMethodReturnsAddressListObject()
    {
        $this->message->addReplyTo('zf-devteam@example.com');
        $replyTo = $this->message->getReplyTo();
        $this->assertTrue(is_array($replyTo));
    }

    public function testReplyToAddressListLivesInHeaders()
    {
        $this->message->addReplyTo('zf-devteam@example.com');
        $replyTo = $this->message->getReplyTo();
        $headers = $this->message->getHeaders(false);
        $this->assertTrue(is_array($headers));
        $this->assertTrue(isset($headers['Reply-To']));
        $header  = $headers['Reply-To'];
        $this->assertSame($header, $replyTo);
    }

    public function testSenderIsNullByDefault()
    {
        $this->assertNull($this->message->getSender());
    }

    public function testNullSenderDoesNotCreateHeader()
    {
        $sender = $this->message->getSender();
        $headers = $this->message->getHeaders(true);
        $this->assertFalse(isset($headers['Sender']));
    }

    public function testSettingSenderCreatesAddressObject()
    {
        $this->message->setSender('zf-devteam@example.com');
        $sender = $this->message->getSender();
        $this->assertInstanceOf(Address::class, $sender);
    }

    public function testCanSpecifyNameWhenSettingSender()
    {
        $this->message->setSender('zf-devteam@example.com', 'ZF DevTeam');
        $sender = $this->message->getSender();
        $this->assertInstanceOf(Address::class, $sender);
        $this->assertEquals('ZF DevTeam', $sender->getName());
    }

    public function testCanProvideAddressObjectWhenSettingSender()
    {
        $sender = new Address('zf-devteam@example.com');
        $this->message->setSender($sender);
        $test = $this->message->getSender();
        $this->assertEquals($sender, $test);
    }

    public function testCanAddFromAddressUsingName()
    {
        $this->message->addFrom('zf-devteam@example.com', 'ZF DevTeam');
        $addresses = $this->message->getFrom();
        $this->assertEquals(1, count($addresses));
        $address = current($addresses);
        $this->assertEquals('zf-devteam@example.com', $address->getEmail());
        $this->assertEquals('ZF DevTeam', $address->getName());
    }

    public function testCanAddFromAddressUsingEmailAndNameAsString()
    {
        $this->message->addFrom('ZF DevTeam <zf-devteam@example.com>');
        $addresses = $this->message->getFrom();
        $this->assertEquals(1, count($addresses));
        $address = current($addresses);
        $this->assertEquals('zf-devteam@example.com', $address->getEmail());
        $this->assertEquals('ZF DevTeam', $address->getName());
    }

    public function testCanAddFromAddressUsingAddressObject()
    {
        $address = new Address('zf-devteam@example.com', 'ZF DevTeam');
        $this->message->addFrom($address);

        $addresses = $this->message->getFrom();
        $this->assertEquals(1, count($addresses));
        $test = current($addresses);
        $this->assertSame($address, $test);
    }

    public function testCanAddManyFromAddressesUsingArray()
    {
        $addresses = [
            'zf-devteam@example.com',
            'zf-contributors@example.com' => 'ZF Contributors List',
            new Address('fw-announce@example.com', 'ZF Announce List'),
        ];
        $this->message->addFrom($addresses);

        $from = $this->message->getFrom();
        $this->assertEquals(3, count($from));

        $this->assertTrue($this->message->hasAddress('from', 'zf-devteam@example.com'));
        $this->assertTrue($this->message->hasAddress('from', 'zf-contributors@example.com'));
        $this->assertTrue($this->message->hasAddress('from', 'fw-announce@example.com'));
    }

    public function testCanAddManyFromAddressesUsingAddressListObject()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addFrom('fw-announce@example.com');
        $this->message->addFrom($list);
        $from = $this->message->getFrom();
        $this->assertEquals(2, count($from));
        $this->assertTrue($this->message->hasAddress('from', 'fw-announce@example.com'));
        $this->assertTrue($this->message->hasAddress('from', 'zf-devteam@example.com'));
    }

    public function testCanSetFromListFromAddressList()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addFrom('fw-announce@example.com');
        $this->message->setFrom($list);
        $from = $this->message->getFrom();
        $this->assertEquals(1, count($from));
        $this->assertFalse($this->message->hasAddress('from', 'fw-announce@example.com'));
        $this->assertTrue( $this->message->hasAddress('from', 'zf-devteam@example.com'));
    }

    public function testCanAddCCAddressUsingName()
    {
        $this->message->addCC('zf-devteam@example.com', 'ZF DevTeam');
        $addresses = $this->message->getCC();
        $this->assertEquals(1, count($addresses));
        $address = current($addresses);
        $this->assertEquals('zf-devteam@example.com', $address->getEmail());
        $this->assertEquals('ZF DevTeam', $address->getName());
    }

    public function testCanAddCCAddressUsingAddressObject()
    {
        $address = new Address('zf-devteam@example.com', 'ZF DevTeam');
        $this->message->addCC($address);

        $addresses = $this->message->getCC();
        $this->assertEquals(1, count($addresses));
        $test = current($addresses);
        $this->assertSame($address, $test);
    }

    public function testCanAddManyCCAddressesUsingArray()
    {
        $addresses = [
            'zf-devteam@example.com',
            'zf-contributors@example.com' => 'ZF Contributors List',
            new Address('fw-announce@example.com', 'ZF Announce List'),
        ];
        $this->message->addCC($addresses);

        $cc = $this->message->getCC();
        $this->assertEquals(3, count($cc));

        $this->assertTrue($this->message->hasAddress('cc', 'zf-devteam@example.com'));
        $this->assertTrue($this->message->hasAddress('cc', 'zf-contributors@example.com'));
        $this->assertTrue($this->message->hasAddress('cc', 'fw-announce@example.com'));
    }

    public function testCanAddManyCCAddressesUsingAddressListObject()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addCC('fw-announce@example.com');
        $this->message->addCC($list);
        $cc = $this->message->getCC();
        $this->assertEquals(2, count($cc));
        $this->assertTrue($this->message->hasAddress('cc', 'fw-announce@example.com'));
        $this->assertTrue($this->message->hasAddress('cc', 'zf-devteam@example.com'));
    }

    public function testCanSetCCListFromAddressList()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addCC('fw-announce@example.com');
        $this->message->setCC($list);
        $cc = $this->message->getCC();
        $this->assertEquals(1, count($cc));
        $this->assertFalse($this->message->hasAddress('cc', 'fw-announce@example.com'));
        $this->assertTrue($this->message->hasAddress('cc', 'zf-devteam@example.com'));
    }

    public function testCanAddBCCAddressUsingName()
    {
        $this->message->addBCC('zf-devteam@example.com', 'ZF DevTeam');
        $addresses = $this->message->getBCC();
        $this->assertEquals(1, count($addresses));
        $address = current($addresses);
        $this->assertEquals('zf-devteam@example.com', $address->getEmail());
        $this->assertEquals('ZF DevTeam', $address->getName());
    }

    public function testCanAddBCCAddressUsingAddressObject()
    {
        $address = new Address('zf-devteam@example.com', 'ZF DevTeam');
        $this->message->addBCC($address);

        $addresses = $this->message->getBCC();
        $this->assertEquals(1, count($addresses));
        $test = current($addresses);
        $this->assertSame($address, $test);
    }

    public function testCanAddManyBCCAddressesUsingArray()
    {
        $addresses = [
            'zf-devteam@example.com',
            'zf-contributors@example.com' => 'ZF Contributors List',
            new Address('fw-announce@example.com', 'ZF Announce List'),
        ];
        $this->message->addBCC($addresses);

        $bcc = $this->message->getBCC();
        $this->assertEquals(3, count($bcc));

        $this->assertTrue($this->message->hasAddress('bcc', 'zf-devteam@example.com'));
        $this->assertTrue($this->message->hasAddress('bcc', 'zf-contributors@example.com'));
        $this->assertTrue($this->message->hasAddress('bcc', 'fw-announce@example.com'));
    }

    public function testCanAddManyBCCAddressesUsingAddressListObject()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addBCC('fw-announce@example.com');
        $this->message->addBCC($list);
        $bcc = $this->message->getBCC();
        $this->assertEquals(2, count($bcc));
        $this->assertTrue($this->message->hasAddress('bcc', 'fw-announce@example.com'));
        $this->assertTrue($this->message->hasAddress('bcc', 'zf-devteam@example.com'));
    }

    public function testCanSetBCCListFromAddressList()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addBCC('fw-announce@example.com');
        $this->message->setBCC($list);
        $bcc = $this->message->getBCC();
        $this->assertEquals(1, count($bcc));
        $this->assertFalse($this->message->hasAddress('bcc', 'fw-announce@example.com'));
        $this->assertTrue($this->message->hasAddress('bcc', 'zf-devteam@example.com'));
    }

    public function testCanAddReplyToAddressUsingName()
    {
        $this->message->addReplyTo('zf-devteam@example.com', 'ZF DevTeam');
        $addresses = $this->message->getReplyTo();
        $this->assertEquals(1, count($addresses));
        $address = current($addresses);
        $this->assertEquals('zf-devteam@example.com', $address->getEmail());
        $this->assertEquals('ZF DevTeam', $address->getName());
    }

    public function testCanAddReplyToAddressUsingAddressObject()
    {
        $address = new Address('zf-devteam@example.com', 'ZF DevTeam');
        $this->message->addReplyTo($address);

        $addresses = $this->message->getReplyTo();
        $this->assertEquals(1, count($addresses));
        $test = current($addresses);
        $this->assertSame($address, $test);
    }

    public function testCanAddManyReplyToAddressesUsingArray()
    {
        $addresses = [
            'zf-devteam@example.com',
            'zf-contributors@example.com' => 'ZF Contributors List',
            new Address('fw-announce@example.com', 'ZF Announce List'),
        ];
        $this->message->addReplyTo($addresses);

        $replyTo = $this->message->getReplyTo();
        $this->assertEquals(3, count($replyTo));

        $this->assertTrue($this->message->hasAddress('reply-to', 'zf-devteam@example.com'));
        $this->assertTrue($this->message->hasAddress('reply-to', 'zf-contributors@example.com'));
        $this->assertTrue($this->message->hasAddress('reply-to', 'fw-announce@example.com'));
    }

    public function testCanAddManyReplyToAddressesUsingAddressListObject()
    {
        $list[] = 'zf-devteam@example.com';

        $this->message->addReplyTo('fw-announce@example.com');
        $this->message->addReplyTo($list);
        $replyTo = $this->message->getReplyTo();
        $this->assertEquals(2, count($replyTo));
        $this->assertTrue(in_array('fw-announce@example.com', $replyTo));
        $this->assertTrue(in_array('zf-devteam@example.com', $replyTo));
    }

    public function testCanSetReplyToListFromAddressList()
    {
        $list = ['zf-devteam@example.com'];

        $this->message->addReplyTo('fw-announce@example.com');
        $this->message->setReplyTo($list);
        $replyTo = $this->message->getReplyTo();
        $this->assertEquals(1, count($replyTo));
        $this->assertFalse(in_array('fw-announce@example.com', $replyTo));
        $this->assertTrue(in_array('zf-devteam@example.com', $replyTo));
    }

    public function testSubjectIsEmptyByDefault()
    {
        $this->assertNull($this->message->getSubject());
    }

    public function testSubjectIsMutable()
    {
        $this->message->setSubject('test subject');
        $subject = $this->message->getSubject();
        $this->assertEquals('test subject', $subject);
    }

    public function testSettingSubjectProxiesToHeader()
    {
        $this->message->setSubject('test subject');
        $this->assertEquals('test subject', $this->message->getHeader('Subject', false));
    }

    public function testBodyIsEmptyByDefault()
    {
        $this->assertNull($this->message->getBody());
    }

    public function testMaySetBodyFromString()
    {
        $this->message->setBody('body');
        $this->assertEquals('body', $this->message->getBody());
    }

    public function testMaySetBodyFromStringSerializableObject()
    {
        $object = new TestStringSerializableObject('body');
        $this->message->setBody($object);
        $this->assertEquals('body', $this->message->getBodyText());
    }

    public function testMaySetBodyFromMimeMessage()
    {
        $body = new Mime\Message();
        $this->message->setBody($body);
        $this->assertSame($body, $this->message->getBody());
    }

    public function testMaySetNullBody()
    {
        $this->message->setBody(null);
        $this->assertNull($this->message->getBody());
    }

    public static function invalidBodyValues()
    {
        return [
            [['foo']],
            [true],
            [false],
            [new stdClass],
        ];
    }

    /**
     * @dataProvider invalidBodyValues
     */
    public function testSettingNonScalarNonMimeNonStringSerializableValueForBodyRaisesException($body)
    {
        $this->expectException(MailException::class);
        $this->message->setBody($body);
    }

    public function testSettingBodyFromSinglePartMimeMessageSetsAppropriateHeaders()
    {
        $mime = new Mime\Mime('foo-bar');
        $part = new Mime\Part('<b>foo</b>');
        $part->setType('text/html');
        $body = new Mime\Message();
        $body->setMime($mime);
        $body->addPart($part);

        $this->message->setBody($body);
        $this->assertEquals('1.0', $this->message->getHeader('Mime-Version', false));
        $this->assertEquals('text/html', $this->message->getHeader('Content-type', false));
    }

    public function testSettingUtf8MailBodyFromSinglePartMimeUtf8MessageSetsAppropriateHeaders()
    {
        $mime = new Mime\Mime('foo-bar');
        $part = new Mime\Part('UTF-8 TestString: AaÜüÄäÖöß');
        $part->setType(Mime\Mime::TYPE_TEXT);
        $part->setEncoding(Mime\Mime::ENCODING_QUOTEDPRINTABLE);
        $part->setCharset('utf-8');
        $body = new Mime\Message();
        $body->setMime($mime);
        $body->addPart($part);

        $this->message->setEncoding('UTF-8');
        $this->message->setBody($body);

        $this->assertContains(
            'Content-Type: text/plain;' . Message::HEADER_FOLDING . 'charset="utf-8"' . Message::EOL
            . 'Content-Transfer-Encoding: quoted-printable' . Message::EOL,
            $this->message->getHeader('Content-Type', true)
        );
    }

    public function testSettingBodyFromMultiPartMimeMessageSetsAppropriateHeaders()
    {
        $mime = new Mime\Mime('foo-bar');
        $text = new Mime\Part('foo');
        $text->setType('text/plain');
        $html = new Mime\Part('<b>foo</b>');
        $html->setType('text/html');
        $body = new Mime\Message();
        $body->setMime($mime);
        $body->addPart($text);
        $body->addPart($html);

        $this->message->setBody($body);
        $headers = $this->message->getHeaders(true);
        $this->assertTrue(is_array($headers));

        $this->assertEquals('1.0', $this->message->getHeader('mime-version', false));
        $this->assertEquals("multipart/mixed;\r\n boundary=\"foo-bar\"", $this->message->getHeader('content-type', false));
    }

    public function testRetrievingBodyTextFromMessageWithMultiPartMimeBodyReturnsMimeSerialization()
    {
        $mime = new Mime\Mime('foo-bar');
        $text = new Mime\Part('foo');
        $text->setType('text/plain');
        $html = new Mime\Part('<b>foo</b>');
        $html->setType('text/html');
        $body = new Mime\Message();
        $body->setMime($mime);
        $body->addPart($text);
        $body->addPart($html);

        $this->message->setBody($body);

        $text = $this->message->getBodyText();
        $this->assertEquals($body->generateMessage(Message::EOL), $text);
        $this->assertContains('--foo-bar', $text);
        $this->assertContains('--foo-bar--', $text);
        $this->assertContains('Content-Type: text/plain', $text);
        $this->assertContains('Content-Type: text/html', $text);
    }

    public function testEncodingIsAsciiByDefault()
    {
        $this->assertEquals('ASCII', $this->message->getEncoding());
    }

    public function testEncodingIsMutable()
    {
        $this->message->setEncoding('UTF-8');
        $this->assertEquals('UTF-8', $this->message->getEncoding());
    }

    public function testMessageReturnsNonEncodedSubject()
    {
        $this->message->setSubject('This is a subject');
        $this->message->setEncoding('UTF-8');
        $this->assertEquals('This is a subject', $this->message->getSubject());
    }

    public function testSettingNonAsciiEncodingForcesMimeEncodingOfSomeHeaders()
    {
        $this->message->addTo('zf-devteam@example.com', 'ZF DevTeam');
        $this->message->addFrom('matthew@example.com', "Matthew Weier O'Phinney");
        $this->message->addCC('zf-contributors@example.com', 'ZF Contributors List');
        $this->message->addBCC('zf-crteam@example.com', 'ZF CR Team');
        $this->message->setSubject('This is a subject');
        $this->message->setEncoding('UTF-8');

        $test = $this->message->getHeaders(true);

        $expected = '=?UTF-8?Q?ZF=20DevTeam?=';
        $this->assertContains($expected, $test);
        $this->assertContains('<zf-devteam@example.com>', $test);

        $expected = "=?UTF-8?Q?Matthew=20Weier=20O'Phinney?=";
        $this->assertContains($expected, $test, $test);
        $this->assertContains('<matthew@example.com>', $test);

        $expected = '=?UTF-8?Q?ZF=20Contributors=20List?=';
        $this->assertContains($expected, $test);
        $this->assertContains('<zf-contributors@example.com>', $test);

        $expected = '=?UTF-8?Q?ZF=20CR=20Team?=';
        $this->assertContains($expected, $test);
        $this->assertContains('<zf-crteam@example.com>', $test);

        $expected = 'Subject: =?UTF-8?Q?This=20is=20a=20subject?=';
        $this->assertContains($expected, $test);
    }

    public function testDefaultDateHeaderEncodingIsAlwaysAscii()
    {
        $this->message->setEncoding('utf-8');
        $header = $this->message->getHeader('Date', false);
        $date = date('r');
        $date = substr($date, 0, 16);
        $test = $header;
        $test = substr($test, 0, 16);
        $this->assertEquals($date, $test);
    }

    public function testPassEmptyArrayIntoSetPartsOfMimeMessageShouldReturnEmptyBodyString()
    {
        $mimeMessage = new Mime\Message();
        $mimeMessage->setParts([]);

        $this->message->setBody($mimeMessage);
        $this->assertEquals('', $this->message->getBodyText());
    }

    public function messageRecipients()
    {
        return [
            'setFrom' => ['setFrom'],
            'addFrom' => ['addFrom'],
            'setTo' => ['setTo'],
            'addTo' => ['addTo'],
            'setCC' => ['setCC'],
            'addCC' => ['addCC'],
            'setBCC' => ['setBCC'],
            'addBCC' => ['addBCC'],
            'setReplyTo' => ['setReplyTo'],
            'setSender' => ['setSender'],
        ];
    }

    /**
     * @dataProvider messageRecipients
     */
    public function testRaisesExceptionWhenAttemptingToSerializeMessageWithCRLFInjectionViaHeader($recipientMethod)
    {
        $subject = [
            'test1',
            'Content-Type: text/html; charset = "iso-8859-1"',
            '',
            '<html><body><iframe src="http://example.com/"></iframe></body></html> <!--',
        ];
        $this->expectException(\InvalidArgumentException::class);
        $this->message->{$recipientMethod}(implode(Message::EOL, $subject));
    }

    public function testDetectsCRLFInjectionViaSubject()
    {
        $subject = [
            'test1',
            'Content-Type: text/html; charset = "iso-8859-1"',
            '',
            '<html><body><iframe src="http://example.com/"></iframe></body></html> <!--',
        ];
        $this->message->setSubject(implode(Message::EOL, $subject));

        $serializedHeaders = implode(Message::EOL, $this->message->getHeaders(true));
        $this->assertContains('example', $serializedHeaders);
        $this->assertNotContains("\r\n<html>", $serializedHeaders);
    }

    public function testHeaderUnfoldingWorksAsExpectedForMultipartMessages()
    {
        $text = new Mime\Part('Test content');
        $text->setType(Mime\Mime::TYPE_TEXT);
        $text->setEncoding(Mime\Mime::ENCODING_QUOTEDPRINTABLE);
        $text->setDisposition(Mime\Mime::DISPOSITION_INLINE);
        $text->setCharset('UTF-8');

        $html = new Mime\Part('<b>Test content</b>');
        $html->setType(Mime\Mime::TYPE_HTML);
        $html->setEncoding(Mime\Mime::ENCODING_QUOTEDPRINTABLE);
        $html->setDisposition(Mime\Mime::DISPOSITION_INLINE);
        $html->setCharset('UTF-8');

        $multipartContent = new Mime\Message();
        $multipartContent->addPart($text);
        $multipartContent->addPart($html);

        $multipartPart = new Mime\Part($multipartContent->generateMessage());
        $multipartPart->setCharset('UTF-8');
        $multipartPart->setType('multipart/alternative');
        $multipartPart->setBoundary($multipartContent->getMime()->boundary());

        $message = new Mime\Message();
        $message->addPart($multipartPart);

        $this->message->addHeader('Content-Transfer-Encoding', Mime\Mime::ENCODING_QUOTEDPRINTABLE);
        $this->message->setBody($message);

        $contentType = $this->message->getHeader('Content-Type', false);
        $this->assertTrue(is_string($contentType));
        $this->assertContains('multipart/alternative', $contentType);
        $this->assertContains($multipartContent->getMime()->boundary(), $contentType);
    }
}

class TestStringSerializableObject
{
    public function __construct($body)
    {
        $this->body = $body;
    }

    public function __toString()
    {
        return (string)$this->body;
    }
}
