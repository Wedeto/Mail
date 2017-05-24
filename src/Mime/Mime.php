<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Wedeto\Mail\Mime\Mime was adapted from Zend\Mime\Mime.
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

namespace Wedeto\Mail\Mime;

use Wedeto\Util\ErrorInterceptor;

/**
 * Support class for MultiPart Mime Messages
 */
class Mime
{
    const TYPE_OCTETSTREAM = 'application/octet-stream';
    const TYPE_TEXT = 'text/plain';
    const TYPE_HTML = 'text/html';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_QUOTEDPRINTABLE = 'quoted-printable';
    const ENCODING_BASE64 = 'base64';
    const DISPOSITION_ATTACHMENT = 'attachment';
    const DISPOSITION_INLINE = 'inline';
    const LINELENGTH = 76;
    const LINEEND = "\n";
    const MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const MULTIPART_MIXED = 'multipart/mixed';
    const MULTIPART_RELATED = 'multipart/related';
    const CHARSET_REGEX = '#=\?(?P<charset>[\x21\x23-\x26\x2a\x2b\x2d\x5e\5f\60'
                        . '\x7b-\x7ea-zA-Z0-9]+)\?(?P<encoding>[\x21\x23-\x26'
                        . '\x2a\x2b\x2d\x5e\5f\60\x7b-\x7ea-zA-Z0-9]+)\?(?P'
                        . '<text>[\x21-\x3e\x40-\x7e]+)#';
    const MIME_ENCODED_REGEX = "/=\\?([^?]+)\\?(Q|B)\\?([\x30-\x7e]*)\\?=/";
    const MIME_NOT_PRINTABLE_REGEX = '/[^\x20-\x7e]/';

    protected $boundary;
    protected static $makeUnique = 0;

    /**
     * Check if the given string is "printable"
     *
     * Checks that a string contains no unprintable characters. If this returns
     * false, encode the string for secure delivery.
     *
     * @param string $str
     * @return bool
     */
    public static function isPrintable(string $str)
    {
        return !preg_match(Mime::MIME_NOT_PRINTABLE_REGEX, $str);
    }

    /**
     * Encode a string with either base64 or quoted-printable. 
     *
     * @param string $str The string to encode
     * @param string $scheme The scheme to use - Mime::ENCODING_QUOTEDPRINTABLE or Mime::ENCODING_BASE64
     * @param int $length The maximum line length
     * @param string $eol The split for lines. Mime::EOL or Mime::EOL_FOLD should be used.
     * @param bool $strip_charset False to leave the charset identifier in the
     *                            encoded string, true to return only the
     *                            encoded data. The former should be used in
     *                            headers.
     * @return string The encoded string
     */
    public static function encode(
        string $str,
        string $scheme,
        string $prefix,
        string $eol,
        bool $strip_charset
    )
    {
        if ($scheme === Mime::ENCODING_7BIT || $scheme === Mime::ENCODING_8BIT)
            return $str;

        if ($scheme === self::ENCODING_QUOTEDPRINTABLE || $scheme === 'Q')
            $scheme = 'Q';
        elseif ($scheme === self::ENCODING_BASE64 || $scheme === 'B')
            $scheme = 'B';
        else
            throw new \InvalidArgumentException("Invalid encoding scheme: " . $scheme);

        $prefix_length = strlen($prefix);

        // Use mb_internal_encoding and fallback to UTF-8
        $encoding = mb_internal_encoding() ?? "UTF-8";

        // Check if conversion is valid
        $detected = mb_detect_encoding($str, mb_detect_order(), true);
        if ($detected !== $encoding && $detected !== 'ASCII')
            return false;

        $str = mb_encode_mimeheader($str, $encoding, $scheme, $eol, $prefix_length);

        // In header fields, the charset is encoded in the encoded string. In body fields,
        // the appropriate charset is passed as a separate header
        if ($strip_charset)
            $str = preg_replace(Mime::MIME_ENCODED_REGEX, "\\3", $str);

        return $prefix . $str;
    }

    /**
     * Create a new Mime encoding utility instance
     *
     * @param string $boundary
     */
    public function __construct($boundary = "")
    {
        // This string needs to be somewhat unique
        if (empty($boundary))
            $this->boundary = '=_' . md5(microtime(1) . self::$makeUnique++);
        else
            $this->boundary = $boundary;
    }

    /**
     * Return the MIME boundary
     *
     * @return string The MIME boundary
     */
    public function boundary()
    {
        return $this->boundary;
    }

    /**
     * Return a MIME boundary line
     *
     * @param string $EOL Defaults to \r\n
     * @return string The MIME starting delimiter
     */
    public function boundaryLine(string $EOL = self::LINEEND)
    {
        return $EOL . '--' . $this->boundary . $EOL;
    }

    /**
     * Return MIME ending
     *
     * @param string $EOL Defaults to \r\n
     * @return string The MIME end delimiter
     */
    public function mimeEnd(string $EOL = self::LINEEND)
    {
        return $EOL . '--' . $this->boundary . '--' . $EOL;
    }

    /**
     * Detect MIME charset
     *
     * Extract parts according to https://tools.ietf.org/html/rfc2047#section-2
     *
     * @param string $str The string to perform detection on
     * @return string The mime charset
     */
    public static function mimeDetectCharset(string $str)
    {
        if (preg_match(self::CHARSET_REGEX, $str, $matches))
            return strtoupper($matches['charset']);

        return 'ASCII';
    }
}
