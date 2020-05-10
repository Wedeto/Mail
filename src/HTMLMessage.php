<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Copyright 2017, Egbert van der Wal.

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

namespace Wedeto\Mail;

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
        $header = $type . ';' . Header::EOL . ' boundary="' . $mime->boundary() . '"';
        $this->addHeader('Content-Type', $header);
        $this->body->setType($type);
        return $this;
    }

    public function setHTML(string $html)
    {
        $this->html_part->setContent($html);
        return $this;
    }

    public function getHTMLPart() {
        return $this->html_part;
    }

    public function setPlain(string $text)
    {
        $this->plain_part->setContent($text);
        return $this;
    }

    public function getPlainPart() {
        return $this->plain_part;
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
     * @return Wedeto\Mail\Mime\Attachment The attachment object
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
            $this->message_wrapper->addPart($this->message);

            $this->setBody($this->message_wrapper);
            $this->setContentType(Mime\Mime::MULTIPART_MIXED);
            $this->message->setType(Mime\Mime::MULTIPART_ALTERNATIVE);
        }

        $attachment = new Mime\Attachment($filename, $resource, $mime);
        $this->body->addPart($attachment);
        return $attachment;
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

        $attachment = new Mime\Attachment($filename, $resource, $mime);
        $attachment->setDisposition(Mime\Mime::DISPOSITION_INLINE);
        $attachment->generateId();
        $this->html_message->addPart($attachment);
        return "cid:" . $attachment->getId();
    }
}
