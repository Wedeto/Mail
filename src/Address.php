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
*/

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\Mail;

use Wedeto\Mail\Mime\Mime;

/**
 * Represent an e-mail address, and convert it to a string ready for e-mail sending
 */
class Address 
{
    /** The e-mail address */
    protected $email;

    /** The name */
    protected $name;

    /**
     * Create the addres instance
     *
     * @param string $email The e-mail address
     * @param string $name The name of the recipient
     * @throws InvalidArgumentException when the e-mail address is not valid
     */
    public function __construct(string $email, string $name = null)
    {
        $filtered = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($filtered === false && mb_strlen($email) !== strlen($email))
        {
            if (!function_exists('idn_to_ascii'))
                throw new \RuntimeException("E-mail address contains multi-byte characters but php-idn extension is not available");

            // Unicode needs to be punycoded
            $parts = explode('@', $email);
            if (count($parts) === 2)
            {
                $punycoded = $parts[0] . '@' . idn_to_ascii($parts[1]);
                $filtered = filter_var($punycoded, FILTER_VALIDATE_EMAIL);
            }
        }
        
        if ($filtered === false)
            throw new \InvalidArgumentException('Not a valid e-mail address: ' . $email);

        $this->email = $email;
        $this->name = preg_replace('/\p{Cc}/u', '', $name);
    }

    /**
     * @return string The e-mail address
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string The name
     */
    public function getName()
    {
        return empty($this->name) ? null : $this->name;
    }
    
    /**
     * @return string String representation of address
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string String representation of address
     */
    public function toString()
    {
        // E-mail address part
        $email = $this->getEmail();

        if (preg_match('/^(.+)@([^@]+)$/', $email, $matches))
        {
            $localPart = $matches[1];
            $hostname = \idn_to_ascii($matches[2]);
            $email = sprintf('%s@%s', $localPart, $hostname);
        }

        // Name part
        $name = $this->getName();
        if (empty($name))
            return $email;

        if (strpos($name, ',') !== false)
            $name = sprintf('"%s"', str_replace('"', '\\"', $name));

        if (!Mime::isPrintable($name))
            $name = HeaderWrap::mimeEncodeValue($name, 'UTF-8');

        return sprintf('%s <%s>', $name, $email);
    }
}
