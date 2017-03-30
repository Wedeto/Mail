<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Wedeto\Mail\HeaderWrap was adapted from Zend\Mail\Header\HeaderWrap.
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

use Wedeto\Mail\Mime\Mime;

/**
 * Utility class used for creating wrapped or MIME-encoded versions of header
 * values.
 */
class HeaderWrap
{
    /**
     * Wrap a long header line
     *
     * @param  string $value
     * @param  HeaderInterface $header
     * @return string The wrapped header value
     */
    public static function wrap(string $value)
    {
        $encoding = Mime::isPrintable($value) ? "ASCII" : "UTF-8";
        if ($encoding === 'ASCII')
            return wordwrap($value, 78, "\r\n ");

        return self::mimeEncodeValue($value, $encoding, 78);
    }

    /**
     * MIME-encode a value
     *
     * Performs quoted-printable encoding on a value, setting maximum
     * line-length to 998.
     *
     * @param  string $value
     * @param  string $encoding
     * @param  int $lineLength maximum line-length, by default 998
     * @return string Returns the mime encode value without the last line ending
     */
    public static function mimeEncodeValue(string $value, string $encoding, int $lineLength = 998)
    {
        return Mime::encodeQuotedPrintableHeader($value, $encoding, $lineLength, "\r\n");
    }

    /**
     * Test if it is possible to apply MIME-encoding
     *
     * @param string $value The value that should be encodede
     * @return bool True when the value can be encoded
     */
    public static function canBeEncoded(string $value)
    {
        // avoid any wrapping by specifying line length long enough
        // "test" -> 4
        // "x-test: =?ISO-8859-1?B?dGVzdA==?=" -> 33
        //  8       +2          +3         +3  -> 16
        $charset = 'UTF-8';
        $lineLength = strlen($value) * 4 + strlen($charset) + 16;

        $preferences = [
            'scheme' => 'Q',
            'input-charset' => $charset,
            'output-charset' => $charset,
            'line-length' => $lineLength,
        ];

        $encoded = iconv_mime_encode('x-test', $value, $preferences);

        return $encoded !== false;
    }
}
