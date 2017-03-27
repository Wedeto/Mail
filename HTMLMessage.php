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

namespace WASP\Mail;

class HTMLMessage extends Message
{
    protected $message;
    protected $html_message = null;
    protected $html_part;
    protected $plain_part;
    protected $message_wrapper = null;

    public function __construct()
    {
        parent::__construct();

        // The HTML part is the HTML of the message
        $this->html_part = new Mime\Part;
        $this->html_part->setType(Mime\Mime::TYPE_HTML);

        // The plain part is the plain text representation of the message
        $this->plain_part = new Mime\Part;
        $this->plain_part->setType(Mime\Mime::TYPE_TEXT);

        // The message part is a text / html alternative Mime message
        $this->message = new Mime\Message;
        $this->message->addPart($this->plain_part);
        $this->message->addPart($this->html_part);
        $this->message->setType(Mime\Mime::MULTIPART_ALTERNATIVE);

        // The outer Mime message is the text message and attachments
        $this->setBody($this->message);
        $this->setContentType(Mime\Mime::MULTIPART_ALTERNATIVE);
    }

    public function setContentType(string $type)
    {
        $mime = $this->body->getMime();
        $header = $type . ';' . self::EOL . ' boundary="' . $mime->boundary() . '"';
        $this->addHeader('Content-Type', $header);
        return $this;
    }

    public function setHTML(string $html)
    {
        $this->html_part->setContent($html);
        return $this;
    }

    public function setPlain(string $text)
    {
        $this->plain_part->setContent($text);
        return $this;
    }

    public function getBodyText()
    {
        $txt = $this->plain_part->getRawContent();
        if (empty($txt))
        {
            $html = $this->html_part->getRawContent();

            if (empty($html))
                throw new MailException("Not forming empty e-mail message");

            // Generate a rudimentary text representation of the HTML message
            $plain = preg_replace('/<br[^>]*>/', "\n", $html);
            $plain = str_replace('</p>', "\n", $plain);
            $plain = strip_tags($plain);
            $this->plain_part->setContent($plain);
        }

        return parent::getBodyText();
    }

    /**
     * Attach an attachment to the message
     * @param string $filename The file name to attach. Will be opened if no resource is provided
     * @param resource $resource An opened file stream to be used as
     *                           attachment. If set to null, $filename is
     *                           opened.
     * @param string $mime The Mime type. If empty it will be deduced from the file
     * @return WASP\Mail\Mime\Attachment The attachment object
     */
    public function attach(string $filename, $resource = null, string $mime = "")
    {
        if ($this->message_wrapper === null)
        {
            // We need an outer wrapper to put the attachment separate from the
            // text / html alternatives, to get this structure:
            // - body -> multipart/mixed
            //  - message -> multipart/alternative 
            //  - attachments
            $this->message_wrapper = new Mime\Message;
            $this->message_wrapper->setType(Mime\Mime::MULTIPART_ALTERNATIVE);
            $this->setContentType(Mime\Mime::MULTIPART_RELATED);
            $this->message_wrapper->addPart($this->message);
        }

        $attachment = new Mime\Attachment($filename, $resource);
        $this->message_wrapper->addPart($attachment);

        $this->setBody($this->message_wrapper);
        return $this;
    }

    /**
     * Embed a file as an asset to be used within the HTML, most likely an image. 
     * It will add the image in a multipart/related Mime-structure.
     *
     * @param string $filename The file name to embed. Will be opened if no resource is provided
     * @param resource $resource An opened file stream to be used as
     *                           attachment. If set to null, $filename is
     *                           opened.
     * @param string $mime The Mime type. If empty it will be deduced from the file
     * @return string The Mime part ID, with a cid: prefix so it can be used directly.
     */
    public function embed(string $filename, $resource = null, string $mime = "")
    {
        if ($this->html_message === null)
        {
            // We need an inner wrapper around the HTML part to add the related assets,
            // to get this structure:
            // - message -> multipart/alternative
            //  - plaintext -> text/plain
            //  - html_message -> multipart/related
            //   - html -> text/html
            //   - embedded asset
            $this->message->removePart($this->html_part);
            $this->html_message = new Mime\Message; 
            $this->html_message->setType(Mime\Mime::MULTIPART_RELATED);
            $this->html_message->addPart($this->html_part);
            $this->message->addPart($this->html_message);
        }

        $attachment = new Mime\Attachment($filename);
        $attachment->setDisposition(Mime\Mime::DISPOSITION_INLINE);
        $attachment->generateId();
        $this->html_message->addPart($attachment);
        return "cid:" . $attachment->getId();
    }
}
