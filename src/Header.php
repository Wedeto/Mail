<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Wedeto\Mail\Header was adapted from Zend\Mail\Headers.
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
use Iterator;

use Wedeto\Mail\Mime\Mime;

class Header implements Iterator
{
    const FORMAT_RAW = 'RAW';
    const FORMAT_ENCODED = 'ENCODED';
    const EOL = "\r\n";
    const EOL_FOLD = "\r\n ";
    const ALLOWABLE_DATE_WINDOW = 365 * 86400; // 1 year

    protected static $ADDRESS_HEADERS = ['From', 'To', 'Reply-To', 'Cc', 'Bcc', 'Sender'];
    protected static $STRUCTURED_HEADERS = ['Date', 'Received'];
    protected static $RESTRICTED_HEADERS = ['Return-Path'];
    
    protected $encoding = 'ASCII';
    protected $header_fields = [];

    protected $keys = [];
    protected $iterator = 0;

    /**
     * Check if the header has a value for the specified header field
     * @param string $name The name of the header field
     * @return bool True if there is a value for $name, false if not.
     */
    public function has(string $name)
    {
        $name = self::normalizeHeader($name);
        return !empty($this->header_fields[$name]);
    }

    /**
     * Get a value for a header field
     * @param string $name The header field
     * @param string $format Either FORMAT_RAW or FORMAT_ENCODED. RAW is the raw value as stored,
     *                       FORMAT_ENCODED will encode and fold the value and prepend it with the
     *                       header prefix.
     * @return mixed The value for the header field, optionally encoded
     */
    public function get(string $name, string $format = Header::FORMAT_RAW)
    {
        $name = self::normalizeHeader($name);
        if (!isset($this->header_fields[$name]))
            return null;

		$value = $this->header_fields[$name];
		if ($format === Header::FORMAT_RAW)
			return $value;

		if ($format !== Header::FORMAT_ENCODED)
			throw new \InvalidArgumentException("Invalid format: " . $format);

		switch ($name)
		{
            case 'From':
            case 'To':
            case 'Reply-To':
            case 'Cc':
            case 'Bcc':
            case 'Sender':
                return $this->getAddress($name, $format);
            case 'Content-Type':
                return $this->getContentType($format);
            case 'Date':
                return $name . ': ' . $value;
			default:
				return $name . ': ' . $this->wrap($value);
		}
    }

    public static function wrap(string $value, bool $always_encode = false, string $prefix = '', string $eol = Header::EOL_FOLD)
    {
        if (!$always_encode && Mime::isPrintable($value))
            return $prefix . wordwrap($value, 76, Header::EOL_FOLD);

        return Mime::encode($value, 'Q', $prefix, $eol, false);
    }

    public static function canBeEncoded(string $value)
    {
        return Mime::encode($value, 'Q', '', Header::EOL_FOLD, false) !== false;
    }

    /**
     * Return a formatted address header.
     *
     * @param string $name The header to return
     * @return string Returns a properly encoded, folded header
     */
    public function getAddress(string $name, string $format = Header::FORMAT_RAW)
    {
        $name = self::normalizeHeader($name);
        if (!in_array($name, self::$ADDRESS_HEADERS))
            throw new \InvalidArgumentException("Header name is not an address header: " . $name);

        $value = $this->header_fields[$name] ?? null;
        if ($format === Header::FORMAT_RAW || empty($value))
            return $value;

        $first = array_shift($value);
        $values = [$name . ': ' . $first->toString()];
        foreach ($value as $address)
            $values[] = $address->toString();

        return implode(',' . self::EOL_FOLD, $values);
    }

    /**
     * Set a header value
     * @param string $name The header field to set
     * @param mixed $value What to set it to
     * @return Header Provides fluent interface
     */
    public function set(string $name, $value)
    {
        $name = self::normalizeHeader($name);

        if (empty($value))
        {
            unset($this->header_fields[$name]);
            return $this;
        }

        switch ($name)
        {
            case 'From':
            case 'To':
            case 'Reply-To':
            case 'Cc':
            case 'Bcc':
            case 'Sender':
                return $this->setAddress($name, $value);
            case 'Content-Type':
                return $this->setContentType($value);
            case 'Date':
                return $this->setDate($value);
            default:
                return $this->setHeader($name, $value);
        }
    }

    /**
     * A wrapper for addAddress to replace all addresses in one of the address
     * headers.
     * 
     * @param string $header The name of the address field
     * @param mixed $email The address to append. Can be anything Address
     *                      accepts in the constructor, or an Address object
     * @param string $name The name for the address
     * @return Header Provides fluent interface
     */
	public function setAddress(string $header, $email, $name = null)
    {
        return $this->addAddress($header, $email, $name, false);
    }

    /**
     * Set an address value
     * @param string $header The name of the address field
     * @param mixed $value The address to append. Can be anything Address
     *                      accepts in the constructor, or an Address object
     * @param string $name The name for the address.
     * @param bool $append Whether to append the address
     */
	public function addAddress(string $header, $email, string $name = null, bool $append = true)
	{
        $header = self::normalizeHeader($header);
        if (!in_array($header, self::$ADDRESS_HEADERS))
            throw new \InvalidArgumentException("Header name is not an address header: " . $name);

        if ($header === 'Sender' && $append && !empty($this->header_fields[$header]))
            throw new MailException("Only one Sender can be set in a e-mail message");

		if (!($email instanceof Address))
			$email = new Address($email, $name);

		if (!$append)
			$this->header_fields[$header] = [];

		$this->header_fields[$header][$email->getEmail()] = $email;
        return $this;
	}

    /**
     * Set the content type
     *
     * @param string $value The value to set
     * @return Header Provides fluent interface
     */
	public function setContentType(string $value)
	{
		$parts = explode(";", $value);
		$type = array_shift($parts);
		$parameters = [];
		foreach ($parts as $part)
		{
            $part = trim($part);
			list($key, $value) = explode('=', $part, 2);
			$value = trim($value, "'\" \t\n\r\0\x0B");
			$parameters[$key] = $value;
		}

		$this->header_fields['Content-Type'] = [
			'type' => $type,
			'parameters' => $parameters
		];
        return $this;
	}

    /**
     * Return the Content-Type header field
     * @param string $format FORMAT_RAW or FORMAT_ENCODED
     * @return string|array Depending on format, either the formatted header line, or the content-type structure
     */
    public function getContentType(string $format = Header::FORMAT_RAW)
    {
        $value = $this->header_fields['Content-Type'] ?? null;
        if ($format === Header::FORMAT_RAW || empty($value))
            return $value;

        $type = $value['type'];
        $keywords = $value['parameters'];

        $values = [$type];
        foreach ($keywords as $key => $value)
            $values[] = sprintf('%s="%s"', $key, $this->wrap($value));

        return 'Content-Type: ' . implode(';' . self::EOL_FOLD, $values);
    }

    /**
     * Set the timestamp of the message.
     *
     * @param string $value The value to set. Can be DateTime, IntlCalendar, a date string or a unix timestamp.
     *                      Strings are converted to a unix timestamp. All unix timestamps are checked to see if
     *                      they fall within reasonable bounds (a year from now).
     * @return Header Provide fluent interface
     */
    public function setDate($value)
    {
        if ($value instanceof \IntlCalendar)
            $value = $value->toDateTime();

        if (is_string($value))
            $value = new \DateTime($value);

        if (is_int($value))
        {
            $lower_bound = time() - self::ALLOWABLE_DATE_WINDOW;
            $upper_bound = time() + self::ALLOWABLE_DATE_WINDOW;
            if ($value >= $lower_bound && $value <= $upper_bound)
                $value = new \DateTime('@' . $value);
        }

        // Validate
        if (!($value instanceof \DateTimeInterface))
            throw new \InvalidArgumentException("Date must be DateTime, IntlCalendar, date string or valid Unix timestamp");

        $value = $value->format('r');
        $this->header_fields['Date'] = $value;
        return $this;
    }

    /**
     * Set a header value
     * @param string $name The name to set
     * @param string $value The value to set
     * @return Header Provide fluent interface
     */
	protected function setHeader(string $name, string $value)
	{
        $structured = in_array($name, self::$STRUCTURED_HEADERS);
        $address = in_array($name, self::$ADDRESS_HEADERS);

        if ($structured || $address)
            throw new \LogicException("setHeader should not be reachable with structured or address headers");

        if (empty($value))
            unset($this->header_fields[$name]);
        else
            $this->header_fields[$name] = $value;
        return $this;
	}

    /**
     * Magic method to convert the Header object to a string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /** 
     * @return string The formatted, encoded header
     */
    public function toString()
    {
        $header_lines = [];
        foreach (array_keys($this->header_fields) as $field)
        {
            if ($field === 'Bcc')
                continue;
            $header_lines[] = $this->get($field, Header::FORMAT_ENCODED);
        }
        return implode(Header::EOL, $header_lines) . Header::EOL;
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

    /**
     * @return string The current header value
     */
    public function current()
    {
        $key = $this->key();
        return $key === "Content-Type" ?
                $this->getContentType(Header::FORMAT_ENCODED)
            :
                $this->getHeader($key, Header::FORMAT_RAW);
    }

    /**
     * Return the currenct key
     */
    public function key()
    {
        return $this->keys[$this->iterator];
    }

    /**
     * Advance the iterator position
     */
    public function next()
    {
        ++$this->iterator;
    }

    /**
     * Set the iterator to the first element
     */
    public function rewind()
    {
        $this->keys = array_keys($this->header_fields);
        $this->iterator = 0;
    }

    /**
     * @return bool True if the iterator is valid, false if it is not
     */
    public function valid()
    {
        return isset($this->keys[$this->iterator]) && isset($this->header_fields[$this->keys[$this->iterator]]);
    }
}
