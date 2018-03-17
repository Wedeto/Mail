<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
Wedeto\Mail is published under the BSD 3-Clause License.

Wedeto\Mail\SMTPSender was adapted from Zend\Mail\Transport\SMTP.
The modifications are: Copyright 2017-2018, Egbert van der Wal.

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

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\Mail;

use Wedeto\Mail\Address;
use Wedeto\Mail\Headers;
use Wedeto\Mail\Message;
use Wedeto\Mail\Protocol;
use Wedeto\Mail\MailException;

use Wedeto\Util\DI\InjectionTrait;

/**
 * SMTP message sender. Used the SMTP protocol implementation to transmit the
 * message.
 */
class SMTPSender
{
    use InjectionTrait;

    /** Can/should be used as singleton */
    const WDI_REUSABLE = true;

    /** SMTP sending options */
    protected $options;

    /** SMTP connection */
    protected $connection;

    /** Whether to disconnect on destruct */
    protected $autoDisconnect = true;

    /**
     * Create the SMTP transport
     *
     * @param array $options The configuration parameters
     */
    public function __construct(MailConfiguration $config)
    {
        $this->connection = new Protocol\SMTP($config);
    }

    /**
     * Set options
     *
     * @param array $options
     * @return Wedeto\Mail\SMTPSender
     */
    public function setOptions(array $options)
    {
        $this->connection->setOptions($options);
        return $this;
    }

    /**
     * Get options
     *
     * @return SMTPOptions
     */
    public function getOptions()
    {
        return $this->connection->getOptions();
    }

    /**
     * Set the automatic disconnection when destruct
     *
     * @param  bool $flag
     * @return SMTP
     */
    public function setAutoDisconnect(bool $flag)
    {
        $this->autoDisconnect = (bool) $flag;
        return $this;
    }

    /**
     * Get the automatic disconnection value
     *
     * @return bool
     */
    public function getAutoDisconnect()
    {
        return $this->autoDisconnect;
    }

    /**
     * Class destructor to ensure all open connections are closed
     */
    public function __destruct()
    {
        if ($this->connection)
        {
            try
            {
                $this->connection->quit();
            }
            catch (ProtocolException\ExceptionInterface $e)
            {} // Ignore

            if ($this->autoDisconnect)
                $this->connection->disconnect();
        }
    }

    /**
     * Sets the connection protocol instance
     *
     * @param Protocol\SMTP $connection
     */
    public function setConnection(Protocol\SMTP $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Gets the connection protocol instance
     *
     * @return Protocol\SMTP
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Disconnect the connection protocol instance
     *
     * @return void
     */
    public function disconnect()
    {
        $this->connection->disconnect();
    }

    /**
     * Send an email via the SMTP connection protocol
     *
     * The connection via the protocol adapter is made just-in-time to allow a
     * developer to add a custom adapter if required before mail is sent.
     *
     * @param Message $message
     * @return bool True when the message has been sent succesfully
     * @throws Exception\RuntimeException
     */
    public function send(Message $message)
    {
        // If sending multiple messages per session use existing adapter
        $connection = $this->getConnection();

        if (!$connection->hasSession())
            $this->connect();
        else
            // Reset connection to ensure reliable transaction
            $connection->rset();

        // Prepare message
        $from = $this->prepareFromAddress($message);
        $recipients = $this->prepareRecipients($message);
        $header = $this->prepareHeader($message);
        $body = $this->prepareBody($message);

        if (count($recipients) === 0)
            throw new MailException('Message must have at least one recipient');

        // Set sender email address
        $connection->mail($from);

        // Set recipient forward paths
        foreach ($recipients as $recipient)
            $connection->rcpt($recipient);

        // Issue DATA command to client
        $connection->data($header . Header::EOL . $body);
        return true;
    }

    /**
     * Retrieve email address for envelope FROM
     *
     * @param  Message $message
     * @throws Exception\RuntimeException
     * @return string
     */
    protected function prepareFromAddress(Message $message)
    {
        $sender = $message->getSender();
        if (!empty($sender))
            return $sender->getEmail();

        $from = $message->getFrom();
        if (!count($from))
            throw new MailException('No sender specified');

        $sender = reset($from);
        return $sender->getEmail();
    }

    /**
     * Prepare array of email address recipients
     *
     * @param  Message $message
     * @return array
     */
    protected function prepareRecipients(Message $message)
    {
        $recipients = array();
        foreach ($message->getTo() as $address)
            $recipients[] = $address->getEmail();
        foreach ($message->getCc() as $address)
            $recipients[] = $address->getEmail();
        foreach ($message->getBcc() as $address)
            $recipients[] = $address->getEmail();

        $recipients = array_unique($recipients);
        return $recipients;
    }

    /**
     * Prepare header string from message
     *
     * @param  Message $message
     * @return string The concatenated header
     */
    protected function prepareHeader(Message $message)
    {
        //$header = $message->getHeader();
        //$header->set('Bcc', null);
        //$val = $header->get('Bcc');
        return $message->getHeader()->toString();
    }

    /**
     * Prepare body string from message
     *
     * @param  Message $message
     * @return string The body text
     */
    protected function prepareBody(Message $message)
    {
        return $message->getBodyText();
    }

    /**
     * Connect the connection, and pass it helo
     *
     * @return Protocol\SMTP
     */
    protected function connect()
    {
        $this->connection->connect();
        $this->connection->helo();
    }
}
