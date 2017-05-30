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

namespace Wedeto\Mail\Mime;

use Wedeto\IO\MimeTypes;
use Wedeto\IO\IOException;
use Wedeto\Mail\Mime\Mime;

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
        if (empty($mime))
        {
            if (is_readable($filename))
            {
                $mime = MimeTypes::getFromFile($filename);
            }
            else
            {
                $detect = MimeTypes::extractFromPath($filename);
                $mime = $detect[0] ?? null;
            }
        }

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
