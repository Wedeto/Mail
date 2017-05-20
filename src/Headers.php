<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Wedeto\Mail\Headers was adapted from Zend\Mail\Headers.
The modifications are: Copyright 2017, Egbert van der Wal.

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

use Traversable;
use Wedeto\Mail\Mime;

class Headers
{
    const FORMAT_RAW = 'RAW';
    const FORMAT_ENCODED = 'ENCODED';

    protected static $ADDRESS_HEADERS = ['From', 'To', 'Reply-To', 'Cc', 'Bcc'];
    protected static $INHERIT_ENCODING_HEADERS = ['Subject'];
    
    protected $encoding = 'ASCII';
    protected $headers = [];

	protected static $iconv_preferences = [
		'scheme' => 'Q',
		'input-charset' => 'utf-8',
		'output-charset' => 'utf-8',
		'line-length' => 76
	];

    public function get(string $name, string $format = Headers::FORMAT_RAW)
    {
        $name = self::normalizeHeader($name);
        if (!isset($this->headers[$name]))
            return null;

		$value = $this->headers[$name];
		if ($format === Headers::FORMAT_RAW)
			return $value;

		if ($format !== Headers::FORMAT_ENCODED)
			throw new \InvalidArgumentException("Invalid format: " . $format);

		switch ($name)
		{
            case 'From':
            case 'To':
            case 'Reply-To':
            case 'Cc':
            case 'Bcc':
                return $this->getAddressHeader($name, $value, false);
			case 'Subject':
				return self::encodeHeader($name, $value, $this->encoding);
			default:
				return self::encodeHeader($name, $value, null);
				return self::isPrintable($value) ? wor('%s: %s', $name, $value) : iconv_mime_encode();
				if (
			
		}
    }

	protected function encodeHeader(string $name, string $value)
	{
		iconv_mime_encode('Subject', $value, self::$iconv_preferences);
	}


	public static function isPrintable(string $str)
	{
		return preg_match('/[^\x30-\x7e]/', $str);
	}

    public function set(string $name, $value)
    {
        $name = self::normalizeHeader($name);

        switch ($name)
        {
            case 'From':
            case 'To':
            case 'Reply-To':
            case 'Cc':
            case 'Bcc':
                $this->setAddress($name, $value, false);
                break;
            case 'Content-Type':
                $this->setContentType($value);
                break;
            default:
                $this->setHeader($name, $value);
        }
    }

	protected function setAddress(string $name, $value, bool $append)
	{
		if (!($value instanceof Address))
			$value = new Address($value);

		if (!$append)
			$this->headers[$name] = [];

		$this->headers[$name] = [$value->getEmail() => $value];
	}

	public function setContentType(string $value)
	{
		$parts = explode(";", $value);
		$type = array_shift($parts);
		$parameters = [];
		foreach ($parts as $part)
		{
			list($key, $value) = explode('=', $part, 2);
			$value = trim($value, "'\" \t\n\r\0\x0B");
			$parameters[$key] = $value;
		}

		$this->headers['Content-Type'] = [
			'type' => $type,
			'parameters' => $parameters
		];
	}

	protected function setHeader(string $name, string $value)
	{
		$this->headers[$name] = $value;
	}


    public function toString()
    {

    }

    /**
     * Normalize the name of the header: Camel-Cased
     * @param string $name The header to normalize
     * @return string The normalized header name
     */
    protected static function normalizeHeader(string $name)
    {
        $name = strtolower($name);
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '-', $name);
        return $name;
    }
}
