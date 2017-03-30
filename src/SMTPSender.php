<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mail/Transport/Smtp.
 *
 * The Zend framework is published on the New BSD license, and as such,
 * this class is also covered by the New BSD license as a derivative work.
 * The original copyright notice is maintained below.
 */

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace WASP\Mail;

use WASP\Mail\Address;
use WASP\Mail\Headers;
use WASP\Mail\Message;
use WASP\Mail\Protocol;
use WASP\Mail\MailException;

/**
 * SMTP message sender. Used the SMTP protocol implementation to transmit the
 * message.
 */
class SMTPSender
{
    /** SMTP sending options */
    protected $options;

    /** SMTP connection */
    protected $connection;

    /** Whether to disconnect on destruct */
    protected $autoDisconnect = true;

    const EOL = "\r\n";

    /**
     * Create the SMTP transport
     *
     * @param array $options The configuration parameters
     */
    public function __construct(array $options = array())
    {
        $this->connection = new Protocol\SMTP($options);
    }

    /**
     * Set options
     *
     * @param array $options
     * @return WASP\Mail\SMTPSender
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
        $headers = $this->prepareHeaders($message);
        $body = $this->prepareBody($message);

        if (count($recipients) === 0)
            throw new MailException('Message must have at least one recipient');

        // Set sender email address
        $connection->mail($from);

        // Set recipient forward paths
        foreach ($recipients as $recipient)
            $connection->rcpt($recipient);

        // Issue DATA command to client
        $connection->data($headers . self::EOL . $body);
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
     * @return string The concatenated headers
     */
    protected function prepareHeaders(Message $message)
    {
        $headers = $message->getHeaders();
        unset($headers['Bcc']);

        $parts = array();
        foreach ($headers as $name => $value)
        {
            if (is_array($value))
            {
                // Must be e-mail addresses
                $emails = array();
                foreach ($value as $address)
                    $emails[] = $address->toString();
                $parts[] = $name . ': ' . implode(",\r\n ", $emails) . self::EOL;
            }
            else
            {
                if ($name !== "Content-Type")
                    $value = HeaderWrap::wrap($value);
                $parts[] = $name . ': '. $value . self::EOL;
            }
        }

        return implode("", $parts);
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
