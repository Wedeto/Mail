<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Address was adapted from Zend\Mail\Address.
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
 * @covers Wedeto\Mail\Address
 */
class AddressTest extends TestCase
{
    public function testDoesNotRequireNameForInstantiation()
    {
        $address = new Address('wedeto-team@wedeto.net');
        $this->assertEquals('wedeto-team@wedeto.net', $address->getEmail());
        $this->assertNull($address->getName());
    }

    public function testAcceptsNameViaConstructor()
    {
        $address = new Address('wedeto-team@wedeto.net', 'Wedeto DevTeam');
        $this->assertEquals('wedeto-team@wedeto.net', $address->getEmail());
        $this->assertEquals('Wedeto DevTeam', $address->getName());
    }

    public function testToStringCreatesStringRepresentation()
    {
        $address = new Address('wedeto-team@wedeto.net', 'Wedeto DevTeam');
        $this->assertEquals('Wedeto DevTeam <wedeto-team@wedeto.net>', $address->toString());
        $this->assertEquals('Wedeto DevTeam <wedeto-team@wedeto.net>', (string)$address);

        $address = new Address('wedeto-team@wedeto.net');
        $this->assertEquals('wedeto-team@wedeto.net', $address->toString());
        $this->assertEquals('wedeto-team@wedeto.net', (string)$address);

        $address = new Address('wedeto-team@wedeto.net', 'Wédétö');
        $this->assertEquals('=?UTF-8?Q?W=C3=A9d=C3=A9t=C3=B6?= <wedeto-team@wedeto.net>', $address->toString());
        $this->assertEquals('=?UTF-8?Q?W=C3=A9d=C3=A9t=C3=B6?= <wedeto-team@wedeto.net>', (string)$address);

        $address = new Address('wedeto-team@wedeto.net', 'Bar, Foo');
        $this->assertEquals('"Bar, Foo" <wedeto-team@wedeto.net>', $address->toString());
        $this->assertEquals('"Bar, Foo" <wedeto-team@wedeto.net>', (string)$address);
    }

    /**
     * @dataProvider invalidSenderDataProvider
     * @param string $email
     * @param null|string $name
     */
    public function testSetAddressInvalidAddressObject($email, $name)
    {
        $this->expectException(\InvalidArgumentException::class);
        new Address($email, $name);
    }

    public function invalidSenderDataProvider()
    {
        return [
            // Description => [sender address, sender name],
            'Empty' => ['', null],
            'any ASCII' => ['azAZ09-_', null],
            'any UTF-8' => ['ázÁZ09-_', null],

            // CRLF @group ZF2015-04 cases
            ["foo@bar\n", null],
            ["foo@bar\r", null],
            ["foo@bar\r\n", null],
            ["foo@bar", "\r"],
            ["foo@bar", "\n"],
            ["foo@bar", "\r\n"],
            ["foo@bar", "foo\r\nevilBody"],
            ["foo@bar", "\r\nevilBody"],
        ];
    }

    /**
     * @dataProvider validSenderDataProvider
     * @param string $email
     * @param null|string $name
     */
    public function testSetAddressValidAddressObject($email, $name)
    {
        $address = new Address($email, $name);
        $this->assertInstanceOf(Address::class, $address);
    }

    public function validSenderDataProvider()
    {
        return [
            // Description => [sender address, sender name],
            'german IDN' => ['oau@ä-umlaut.de', null],
        ];
    }

    public function testConstructWithArray()
    {
        $address = new Address(['foobar@wedeto.net' => 'Foo Bar']);
        $this->assertEquals('foobar@wedeto.net', $address->getEmail());
        $this->assertEquals('Foo Bar', $address->getName());

        $address = new Address(['foobar@wedeto.net']);
        $this->assertEquals('foobar@wedeto.net', $address->getEmail());
        $this->assertEmpty($address->getName());
    }

    public function testNonStringNonArrayThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid e-mail address');
        $address = new Address(new \StdClass);
    }

    public function testConstructWithEmailAndNameInAString()
    {
        $address = new Address('Foo Bar <foobar@wedeto.net>');
        $this->assertEquals('foobar@wedeto.net', $address->getEmail());
        $this->assertEquals('Foo Bar', $address->getName());
    }
}
