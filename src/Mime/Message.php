<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mime/Message
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

/**
 * Class representing a Mime message
 */
class Message implements PartInterface
{
    /** All parts attached to this message */
    protected $parts = array();

    /** The Mime utility */
    protected $mime = null;

    /** The content type */
    protected $typpe = Mime::MULTIPART_MIXED;

    /**
     * Set content type of the Mime message. Only used when the message is
     * added itself as a Mime part.
     *
     * @param string $type
     * @return WASP\Mail\Mime\Part Provides fluent interface
     */
    public function setType(string $type = Mime::MULTIPART_MIXED)
    {
        if (substr($type, 0, 10) !== "multipart/")
            throw new \InvalidArgumentException("A mime message should be multipart/*");
        $this->type = $type;
        return $this;
    }

    /**
     * @return string The content type of the Mime message
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the list of all WASP\Mail\Mime\Part in the message
     *
     * @return array The list of mime Parts
     */
    public function getParts()
    {
        return $this->parts;
    }

    /**
     * Sets the given array of WASP\Mail\Mime\Part as the array for the message
     *
     * @param array $parts
     */
    public function setParts(array $parts)
    {
        $this->parts = $parts;
    }

    /**
     * Append a new WASP\Mail\Mime\Part to the current message
     *
     * @param \WASP\Mail\Mime\PartInterface $part
     * @throws Exception\InvalidArgumentException
     */
    public function addPart(PartInterface $part)
    {
        foreach ($this->getParts() as $key => $row)
        {
            if ($part == $row)
            {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Provided part %s already defined.',
                    $part->getId()
                ));
            }
        }

        $this->parts[] = $part;
    }

    /**
     * Remove a part from the message
     * @param WASP\Mail\Mime\PartInterface The part to remove
     * @return bool True if the part was removed, false if it was not found
     */
    public function removePart(PartInterface $part)
    {
        foreach ($this->parts as $key => $cpart)
        {
            if ($cpart === $part)
            {
                unset($this->parts[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * Check if message needs to be sent as multipart MIME message or if it has
     * only one part.
     *
     * @return bool
     */
    public function isMultiPart()
    {
        return (count($this->parts) > 1);
    }

    /**
     * Set WASP\Mail\Mime\Mime object for the message
     *
     * This can be used to set the boundary specifically or to use a subclass of
     * WASP\Mail\Mime for generating the boundary.
     *
     * @param \WASP\Mail\Mime\Mime $mime
     */
    public function setMime(Mime $mime)
    {
        $this->mime = $mime;
    }

    /**
     * Returns the WASP\Mail\Mime\Mime object in use by the message
     *
     * If the object was not present, it is created and returned. Can be used to
     * determine the boundary used in this message.
     *
     * @return \WASP\Mail\Mime\Mime
     */
    public function getMime()
    {
        if ($this->mime === null)
            $this->mime = new Mime();

        return $this->mime;
    }

    /**
     * Generate MIME-compliant message from the current configuration
     *
     * This can be a multipart message if more than one MIME part was added. If
     * only one part is present, the content of this part is returned. If no
     * part had been added, an empty string is returned.
     *
     * Parts are separated by the mime boundary as defined in WASP\Mail\Mime\Mime. If
     * {@link setMime()} has been called before this method, the WASP\Mail\Mime\Mime
     * object set by this call will be used. Otherwise, a new WASP\Mail\Mime\Mime object
     * is generated and used.
     *
     * @param string $EOL EOL string; defaults to {@link WASP\Mail\Mime\Mime::LINEEND}
     * @return string
     */
    public function generateMessage(string $EOL = Mime::LINEEND)
    {
        if (!$this->isMultiPart())
        {
            if (empty($this->parts))
                return '';

            $part = current($this->parts);
            $body = $part->getContent($EOL);
        }
        else
        {
            $mime = $this->getMime();

            $boundaryLine = $mime->boundaryLine($EOL);
            $body = 'This is a message in Mime Format.  If you see this, '
                  . "your mail reader does not support this format." . $EOL;

            foreach (array_keys($this->parts) as $p)
            {
                $body .= $boundaryLine
                       . $this->getPartHeaders($p, $EOL)
                       . $EOL
                       . $this->getPartContent($p, $EOL);
            }

            $body .= $mime->mimeEnd($EOL);
        }

        return trim($body);
    }

    /**
     * Get the headers of a given part as an array
     *
     * @param int $partnum
     * @return array
     */
    public function getPartHeadersArray(int $partnum)
    {
        return $this->parts[$partnum]->getHeadersArray();
    }

    /**
     * Get the headers of a given part as a string
     *
     * @param int $partnum
     * @param string $EOL
     * @return string
     */
    public function getPartHeaders(int $partnum, string $EOL = Mime::LINEEND)
    {
        return $this->parts[$partnum]->getHeaders($EOL);
    }

    /**
     * Get the (encoded) content of a given part as a string
     *
     * @param int $partnum
     * @param string $EOL
     * @return string
     */
    public function getPartContent(int $partnum, string $EOL = Mime::LINEEND)
    {
        return $this->parts[$partnum]->getContent($EOL);
    }

    /**
     * The following three methods implement the PartInterface, which allows
     * a Mime message to be added as a part to another Mime message.
     */

    /**
     * Get the Content of the current Mime Part in the given encoding.
     *
     * @param string $EOL The End-of-Line delimiter
     * @return string The encoded string
     */
    public function getContent(string $EOL = Mime::LINEEND)
    {
        return $this->generateMessage($EOL);
    }

    /**
     * Create and return the array of headers for this MIME part
     *
     * @param string $EOL The End-of-Line delimiter
     * @return array The list of headers
     */
    public function getHeadersArray(string $EOL = Mime::LINEEND)
    {
        $mime = $this->getMime();
        $boundary = $mime->boundary();

        $contentType = $this->type;
        $contentType .= ';' . $EOL
                      . " boundary=\"" . $boundary . '"';

        $headers[] = ['Content-Type', $contentType];
        return $headers;
    }

    /**
     * Return the headers for this part as a string
     *
     * @param string $EOL The End-of-Line delimiter
     * @return string The list of headers, combined into one string, glued
     *                together with $EOL
     */
    public function getHeaders(string $EOL = Mime::LINEEND)
    {
        $res = '';
        foreach ($this->getHeadersArray($EOL) as $header)
            $res .= $header[0] . ': ' . $header[1] . $EOL;

        return $res;
    }
}
