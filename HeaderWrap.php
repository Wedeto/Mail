<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mail/Header/HeaderWrap.
 *
 * The Zend framework is published on the New BSD license, and as such,
 * this class is also covered by the New BSD license as a derivative work.
 * The original copyright notice is maintained below.
 */

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace WASP\Mail;

use WASP\Mail\Mime\Mime;

/**
 * Utility class used for creating wrapped or MIME-encoded versions of header
 * values.
 */
class HeaderWrap
{
    /**
     * Wrap a long header line
     *
     * @param  string          $value
     * @param  HeaderInterface $header
     * @return string
     */
    public static function wrap($value)
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
     * @param  int    $lineLength maximum line-length, by default 998
     * @return string Returns the mime encode value without the last line ending
     */
    public static function mimeEncodeValue($value, $encoding, $lineLength = 998)
    {
        return Mime::encodeQuotedPrintableHeader($value, $encoding, $lineLength, "\r\n");
    }

    /**
     * Test if is possible apply MIME-encoding
     *
     * @param string $value
     * @return bool
     */
    public static function canBeEncoded($value)
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

        return (false !== $encoded);
    }
}
