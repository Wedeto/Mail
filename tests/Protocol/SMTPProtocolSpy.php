<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\Mail\Protocol\SMTP was adapted from Zend\Mail\Protocol\Smtp.
The modifications are: Copyright 2017, Egbert van der Wal <wedeto at pointpro dot nl>

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

 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\Mail\Protocol;

/**
 * Test spy to use when testing SMTP protocol
 */
class SMTPProtocolSpy extends SMTP
{
    public $calledQuit = false;
    protected $connect = false;
    protected $mail;
    protected $rcptTest = [];

    protected $expect_response = '';

    public function connect()
    {
        $this->connect = true;

        return true;
    }

    public function disconnect()
    {
        $this->connect = false;
        parent::disconnect();
    }

    public function quit()
    {
        $this->calledQuit = true;
        parent::quit();
    }

    public function rset()
    {
        parent::rset();
        $this->rcptTest = [];
    }

    public function mail(string $from)
    {
        parent::mail($from);
    }

    public function rcpt(string $to)
    {
        parent::rcpt($to);
        $this->rcpt = true;
        $this->rcptTest[] = $to;
    }

    protected function _send(string $request)
    {
        // Save request to internal log
        $this->_addLog($request . self::EOL);
        $this->request = $request;
    }

    public function setExpectResponse(string $resp)
    {
        $this->expect_response = $resp;
    }

    protected function _expect($code, int $timeout = null)
    {
        $this->response = $this->expect_response;
        $this->expect_response = '';
        return $this->response;
    }

    public function isConnected()
    {
        return $this->connect;
    }

    public function getMail()
    {
        return $this->mail;
    }

    public function getRecipients()
    {
        return $this->rcptTest;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function setAuth($status)
    {
        $this->auth = (bool) $status;

        return $this;
    }

    public function getSessionStatus()
    {
        return $this->sess;
    }

    public function setSessionStatus($status)
    {
        $this->sess = (bool) $status;

        return $this;
    }
}
