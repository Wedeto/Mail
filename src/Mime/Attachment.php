<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mime/Part
 *
 * The Zend framework is published on the New BSD license, and as such,
 * this class is also covered by the New BSD license as a derivative work.
 * The original copyright notice is maintained below.
 */

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace WASP\Mail\Mime;

use WASP\HTTP\ResponseTypes;
use WASP\IOException;
use WASP\Mail\Mime\Mime;

/**
 * Class representing a MIME part.
 */
class Attachment extends Part
{
    /**
     * Create an attachment
     * @param string $filename The name of the file. Provide a full path to read the file
     * @param resource $resource The resource to attach. If null, $filename is opened
     * @param string $mime The mime type. If empty, it will be deduced from the file
     */
    public function __construct(string $filename, $resource = null, string $mime = "")
    {
        if (!is_resource($resource))
        {
            if (!file_exists($filename) || !is_readable($filename))
                throw new IOException("Cannot read file " . $filename);
        }

        $basename = basename($filename);
        if (empty($mime) && is_readable($filename))
            $mime = ResponseTypes::getFromFile($filename);
        else
            $mime = ResponseTypes::extractFromPath($filename);

        // Still empty Mime? Use a generic type
        if (empty($mime))
            $mime = Mime::TYPE_OCTETSTREAM;

        // Open the file if no resource was provided
        if (!is_resource($resource))
            $resource = fopen($filename, "r");

        parent::__construct($resource);

        $this->setFilename($basename);
        $this->setDisposition(Mime::DISPOSITION_ATTACHMENT);
        $this->setEncoding(Mime::ENCODING_BASE64);
        $this->setType($mime);
    }
}
