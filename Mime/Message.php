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
class Message
{
    /** All parts attached to this message */
    protected $parts = array();

    /** The Mime utility */
    protected $mime = null;

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
     * @param \WASP\Mail\Mime\Part $part
     * @throws Exception\InvalidArgumentException
     */
    public function addPart(Part $part)
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
}
