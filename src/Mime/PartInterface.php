<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is built upon the Zend\Mail\Message and Zend\Mail\Mime classes
 *
 * The Zend framework is published on the New BSD license, and as such,
 * this class is also published on the New BSD license as a derivative work.
 * The original copyright notice is maintained below.
 */

/**
 * WASP - Copyright 2017, Egbert van der Wal
 *
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace WASP\Mail\Mime;

interface PartInterface
{
    public function getContent(string $EOL = Mime::LINEEND);
    public function getHeadersArray(string $EOL = Mime::LINEEND);
    public function getHeaders(string $EOL = Mime::LINEEND);
}
