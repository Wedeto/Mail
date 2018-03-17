<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Address was adapted from Zend\Mail\Address.
The modifications are: Copyright 2018, Egbert van der Wal <wedeto at pointpro dot nl>

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

namespace Wedeto\Mail;

use Wedeto\Util\Configuration;
use Wedeto\Util\Dictionary;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\ValidationException;
use Wedeto\Util\Validation\Validator;

class MailConfiguration extends configuration
{
    /** The Configuration can be reused */
    const WDI_REUSABLE = true;

    /** It can be auto instantiated, as the dependent Configuration must be present */
    const WDI_NOAUTO = false;

    /**
     * Create a new MailConfig instance. Suitable for use with DI.
     *
     * @param Configuration $config The complete configuration object
     */
    public function __construct(Configuration $config = null)
    {
        if (null === $config)
            $config = new Dictionary;

        // Enforce types for database configuration
        $config = $config->dget('mail', []);
        $allowed = [
            'host' => Type::STRING,
            'port' => new Type(Type::INTEGER, ['unstrict' => true]),
            'auth_type' => new Validator(Type::STRING, ['custom' => function ($v) {
                if (in_array(strtoupper($v), ['PLAIN', 'LOGIN', 'CRAM-MD5']) === false) throw new ValidationException("Invalid authentication type: $v");
                return true;
            }]),
            'username' => Type::STRING,
            'password' => Type::STRING,
            'ssl' => new Validator(Type::STRING, ['custom' => function ($v) {
                if (in_array(strtolower($v), ['ssl', 'tls']) === false) throw new ValidationException("Invalid SSL type: $v");
                return true;
            }]),
            'helo' => Type::STRING
        ];

        parent::__construct($allowed, $config);
    }
}
