<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mail/Message
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

use Traversable;
use WASP\Mail\Mime;

class Message
{
    const EOL = "\r\n";

    /** Content of the message */
    protected $body;

    /** The array of headers */
    protected $headers = array();

    /**
     * Message encoding. Used to determine whether or not to encode headers;
     * defaults to ASCII.
     */
    protected $encoding = 'ASCII';

    /**
     * Create the message, set the date
     */
    public function __construct()
    {
        $this->headers['Date'] = date('r');
    }

    /**
     * Is the message valid?
     *
     * If we don't any From addresses, we're invalid, according to RFC2822.
     *
     * @return bool
     */
    public function isValid()
    {
        $from = $this->getFrom();
        if (!$from instanceof AddressList)
            return false;
        return (bool) count($from);
    }

    /**
     * Set the message encoding
     *
     * @param  string $encoding
     * @return Message
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        $this->getHeaders()->setEncoding($encoding);
        return $this;
    }

    /**
     * Get the message encoding
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    protected static $MULTIVALUED_HEADERS = array('Cc', 'Bcc', 'To', 'From');

    /**
     * Normalize the name of the header: Camel-Cased
     * @param string $name The header to normalize
     * @return string The normalized header name
     */
    protected static function normalizeHeader(string $name)
    {
        $name = strtolower($name);
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '-', $name);
        return $name;
    }

    /**
     * Add a header
     * @param string $name The name of the header
     * @param string $value The value of the header
     */
    public function addHeader(string $name, string $value)
    {
        $name = self::normalizeHeader($name);
        $multi = in_array($name, self::$MULTIVALUED_HEADERS, true);

        if (!HeaderWrap::canBeEncoded($value))
            throw new MailException("Value can not be encoded: " . $value);

        if (!isset($this->headers[$name]) && $multi)
            $this->headers[$name] = array();

        if ($multi)
            $this->headers[$name][] = $value;
        else
            $this->headers[$name] = $value;
    }

    /** 
     * Replace a header
     * @param string $name The name of the header
     * @param string $value The value of the header
     */
    public function replaceHeader(string $name, string $value)
    {
        $name = self::normalizeHeader($name);
        $multi = in_array($name, self::$MULTIVALUED_HEADERS, true);
        if ($multi)
            $this->headers[$name] = array($value);
        else
            $this->headers[$name] = $value;
    }

    /**
     * Access headers collection
     *
     * @return array List of headers
     */
    public function getHeaders()
    {
        if (!isset($this->Headers['Date']))
        return $this->headers;
    }

    /**
     * Set (overwrite) From addresses
     *
     * @param mixed $address E-mail address or array of email => name pairs
     * @param string $name The recipient's name. Omit when using an array as $address
     * @return Message
     */
    public function setFrom(string $address, $name = null)
    {
        $this->headers['From'] = array();
        return $this->addFrom($address, $name);
    }

    /**
     * Add a set of addresses to the specified header
     */
    protected function addAddresses(string $header, $address, $name)
    {
        $header = self::normalizeHeader($header);

        if (!isset($this->headers[$header]))
            $this->headers[$header] = array();

        if (is_string($address) || $address instanceof Address)
        {
            if (!empty($name))
                $address = array($address => $name);
        }

        if (!is_array($address) && !($address instanceof \Traversable))
            throw new MailException("Invalid address provided");
        
        foreach ($address as $key => $value)
        {
            if (is_int($key))
            {
                $email = $value;
                $this->headers[$header][$email] = new Address($email, "");
            }
            elseif ($value instanceof Address)
            {
                $this->headers[$header][$value->getEmail()] = $value;
            }
            else
            {
                $email = $key;
                $name = empty($value) ? "" : $value;
                $this->headers[$header][$email] = new Address($email, $name);
            }
        }

        return $this;
    }

    /**
     * Get the list of addresses for the specified header
     * @return array List of Address objects
     */
    protected function getAddresses(string $header)
    {
        $name = self::normalizeHeader($header);
        if (!isset($this->headers[$name]))
            return array();

        return $this->headers[$name];
    }

    /**
     * Add a "From" address
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function addFrom($address, $name = null)
    {
        return $this->addAddresses('From', $address, $name);
    }

    /**
     * Retrieve list of From senders
     *
     * @return array Pairs of email => name pairs
     */
    public function getFrom()
    {
        return $this->getAddresses('From');
    }

    /**
     * Overwrite the address list in the To recipients
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setTo($address, $name = null)
    {
        $this->headers['To'] = array();
        return $this->addAddresses('To', $address, $name);
    }

    /**
     * Add one or more addresses to the To recipients
     * Appends to the list.
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function addTo($address, $name = null)
    {
        return $this->addAddresses('To', $address, $name);
    }

    /**
     * Access the address list of the To header
     *
     * @return array Pairs of email => name pairs
     */
    public function getTo()
    {
        return $this->getAddresses('To');
    }

    /**
     * Set (overwrite) CC addresses
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setCc($address, $name = null)
    {
        $this->headers['Cc'] = array();
        return $this->addAddresses('Cc', $address, $name);
    }

    /**
     * Add a "Cc" address
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function addCc($address, $name = null)
    {
        return $this->addAddresses('Cc', $address, $name);
    }

    /**
     * Retrieve list of CC recipients
     *
     * @return array Pairs of email => name pairs
     */
    public function getCc()
    {
        return $this->getAddresses('Cc');
    }

    /**
     * Set (overwrite) BCC addresses
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setBcc($address, $name = null)
    {
        $this->headers['Bcc'] = array();
        return $this->addAddresses('Bcc', $address, $name);
    }

    /**
     * Add a "Bcc" address
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function addBcc($address, $name = null)
    {
        return $this->addAddresses('Bcc', $address, $name);
    }

    /**
     * Retrieve list of BCC recipients
     *
     * @return array Pairs of email => name pairs
     */
    public function getBcc()
    {
        return $this->getAddresses('Bcc');
    }

    /**
     * Overwrite the address list in the Reply-To recipients
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setReplyTo($address, $name = null)
    {
        $this->headers['Reply-To'] = array();
        return $this->addAddresses('Reply-To', $address, $name);
    }

    /**
     * Add one or more addresses to the Reply-To recipients
     * Appends to the list.
     *
     * @param mixed $address E-mail address or array of e-mail => name pairs
     * @param string|null $name
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function addReplyTo($address, $name = null)
    {
        return $this->addAddresses('Reply-To', $address, $name);
    }

    /**
     * Access the address list of the Reply-To header
     *
     * @return array Pairs of email => name pairs
     */
    public function getReplyTo()
    {
        return $this->getAddresses('Reply-To');
    }

    /**
     * Set the envelope sender of the e-mail
     *
     * @param mixed $address E-mail address of the sender
     * @param string|null $name The name of the sender
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setSender(string $address, string $name)
    {
        $this->headers['Sender'] = array();
        return $this->addAddresses('Sender', $address, $name);
    }

    /**
     * Retrieve the sender address, if any
     *
     * @return Address The envelope sender or null
     */
    public function getSender()
    {
        $sender = $this->getAddresses('Sender');
        foreach ($sender as $address)
            return $address;
        return null;
    }

    /**
     * Set the message subject header value
     *
     * @param string $subject The subject ot set
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setSubject(string $subject)
    {
        $this->headers['Subject'] = $subject;
        return $this;
    }

    /**
     * @return string The subject of the message. Null if not set
     */
    public function getSubject()
    {
        return isset($this->headers['Subject']) ? $this->headers['Subject'] : null;
    }

    /**
     * Set the message body
     *
     * @param string|\WASP\Mail\Mime\Message $body The message body
     * @throws MailException When an invalid type is provided
     * @return WASP\Mail\Message Provides fluent interface
     */
    public function setBody($body)
    {
        if (!is_string($body) && $body !== null)
        {
            if (!is_object($body))
            {
                throw new MailException(sprintf(
                    '%s expects a string or object argument; received "%s"',
                    __METHOD__,
                    gettype($body)
                ));
            }
            if (!$body instanceof Mime\Message)
            {
                if (!method_exists($body, '__toString'))
                {
                    throw new Exception\InvalidArgumentException(sprintf(
                        '%s expects object arguments of type Zend\Mime\Message or implementing __toString();'
                        . ' object of type "%s" received',
                        __METHOD__,
                        get_class($body)
                    ));
                }
                $body = (string)$body;
            }
        }
        $this->body = $body;

        if (!$this->body instanceof Mime\Message)
            return $this;

        // Get headers, and set Mime-Version header
        $headers = $this->getHeaders();
        $this->headers['Mime-Version'] = '1.0';


        // Multipart content headers
        if ($this->body->isMultiPart())
        {
            $mime = $this->body->getMime();
            $this->headers['Content-Type'] = 'multipart/mixed;' . self::EOL . ' boundary="' . $mime->boundary() . '"';
            return $this;
        }

        // MIME single part headers
        $parts = $this->body->getParts();
        if (!empty($parts))
        {
            $part = array_shift($parts);
            foreach ($part->getHeadersArray(self::EOL) as $vals)
            {
                $name = $vals[0];
                $value = $vals[1];
                $this->addHeader($vals[0], $vals[1]);
            }
        }
        return $this;
    }

    /**
     * Return the currently set message body
     *
     * @return string|WASP\Mail\Mime\Message
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Get the string-serialized message body text
     *
     * @return string The body text
     */
    public function getBodyText()
    {
        if ($this->body instanceof Mime\Message)
            return $this->body->generateMessage(self::EOL);

        return (string)$this->body;
    }

    /**
     * Serialize to string
     *
     * @return string The full e-mail message
     */
    public function toString()
    {
        $headers = $this->getHeaders();
        return $headers->toString()
               . self::EOL
               . $this->getBodyText();
    }
}
