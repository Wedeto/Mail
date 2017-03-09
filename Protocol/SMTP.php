<?php
/**
 * This is part of WASP, the Web Application Software Platform
 * This class is adapted from Zend/Mail/Protocol/Smtp,
 * Zend/Mail/Protocol/Smtp/Plain, Zend/Mail/Protocol/Smtp/Login and
 * Zend/Mail/Protocol/Smtp/Crammd5.
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

namespace WASP\Mail\Protocol;

/**
 * SMTP implementation of Zend\Mail\Protocol\AbstractProtocol
 */
class SMTP extends AbstractProtocol
{
    /** The transport method for the socket */
    protected $transport = 'tcp';

    /** Indicates that a session is requested to be secure */
    protected $secure;

    /** Indicates an smtp session has been started by the HELO command */
    protected $sess = false;

    /** Indicates an smtp AUTH has been issued and authenticated */
    protected $auth = false;

    /** Indicates a MAIL command has been issued */
    protected $mail = false;

    /** Indicates one or more RCTP commands have been issued */
    protected $rcpt = false;

    /** Indicates that DATA has been issued and sent */
    protected $data = null;

    /** Authentication username */
    protected $username;

    /** Authentication password */
    protected $password;


    /**
     * Constructor.
     *
     * The first argument may be an array of all options. If so, it must include
     * the 'host' and 'port' keys in order to ensure that all required values
     * are present.
     *
     * @param array $config The configuration. The default is a connection to localhost
     * @throws ProtocolException On an invalid SSL type
     */
    public function __construct(array $config = array())
    {
        $config = $this->validateOptions($config);

        if (isset($config['ssl']))
        {
            $ssl = $config['ssl'];
            switch (strtolower($config['ssl']))
            {
                case 'tls':
                    $this->secure = 'tls';
                    break;
                case 'ssl':
                    $this->transport = $this->secure = 'ssl';
                    if ($port === null)
                        $port = 465;
                    break;
            }
        }

        // If no port has been specified then check the master PHP ini file. Defaults to 25 if the ini setting is null.
        if (empty($port))
            $port = 25;

        if (isset($config['username']))
            $this->setUsername($config['username']);
        if (isset($config['password']))
            $this->setPassword($config['password']);

        parent::__construct($config);
    }

    public function setOptions(array $config)
    {
        $this->disconnect();
        $config = $this->validateOptions($options);
        $this->config = $config;
        $this->host = $config['host'];
        $this->port = $config['port'];
    }

    /** 
     * Make sure the provided configuration is valid
     *
     * @param array $options The configuration
     * @return array The completed, verified options.
     * @throws ProtocolException When the configuration is incomplete
     */
    public static function validateOptions(array $options)
    {
        if (!empty($options['host']) && !is_string($options['host']))
            throw new ProtocolException('Invalid hostname');

        if (!isset($options['host']))
            $options['host'] = '127.0.0.1';

        if (empty($options['auth_type']) && !empty($options['username']))
            $options['auth_type'] = 'PLAIN';

        if (!empty($options['auth_type']))
        {
            if (!is_string($options['auth_type']))
                throw new ProtocolException("Invalid authentication type");

            $tp = strtoupper($options['auth_type']);
            if (!in_array($options['auth_type'], array('PLAIN', 'LOGIN', 'CRAM-MD5'), true))
                throw new ProtocolExeption('Invalid authentication type: ' . $tp);
            $options['auth_type'] = $tp;

            if (empty($options['username']) || empty($options['password']))
                throw new ProtocolException('Authentication requires username and password');
        }

        if (!empty($options['ssl']))
        {
            if (!is_string($options['ssl']))
                throw new ProtocolException('Invalid SSL type');

            $ssl = strtolower($options['ssl']);
            if (!in_array($ssl, array('ssl', 'tls'), true))
                throw new ProtocolException('Invalid SSL type: ' . $ssl);
            $options['ssl'] = $ssl;
        }

        if (!empty($options['helo']) && !is_string($options['helo']))
            throw new ProtocolException('Invalid HELO specified');

        if (!isset($options['helo']))
            $options['helo'] = '127.0.0.1';

        { // Validate HELO identification
            $f = filter_var($options['helo'], FILTER_VALIDATE_IP);
            if ($f === false)
            {
                $resolved = gethostbyname($f);
                if ($resolved === $f)
                    throw new ProtocolException('Unresolvable HELO specified: ' . $options['helo']);
            }
        }

        if (empty($options['port']))
        {
            if (isset($options['ssl']) && $options['ssl'] === 'ssl')
                $options['port'] = 465;
            elseif (isset($options['ssl']) && $options['ssl'] === 'tls')
                $options['port'] = 587;
            else
                $options['port'] = 25;
        }

        return $options;
    }



    /**
     * Connect to the server with the parameters given in the constructor.
     *
     * @return bool If the connection succeeded
     */
    public function connect()
    {
        return $this->_connect($this->transport . '://' . $this->host . ':' . $this->port);
    }


    /**
     * Initiate HELO/EHLO sequence and set flag to indicate valid smtp session
     *
     * @throws ProtocolException When SSL negotiation fails
     */
    public function helo()
    {
        $host = $this->config['helo'];

        // Respect RFC 2821 and disallow HELO attempts if session is already initiated.
        if ($this->sess === true)
            throw new ProtocolException('Cannot issue HELO to existing session');

        // Initiate helo sequence
        $this->_expect(220, 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2
        $this->ehlo($host);

        // If a TLS session is required, commence negotiation
        if ($this->secure == 'tls')
        {
            $this->_send('STARTTLS');
            $this->_expect(220, 180);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
                throw new ProtocolException('Unable to connect via TLS');
            $this->ehlo($host);
        }

        $this->startSession();
        $this->auth();
    }

    /**
     * @return bool The perceived session status
     */
    public function hasSession()
    {
        return $this->sess;
    }

    /**
     * Send EHLO or HELO depending on capabilities of smtp host
     *
     * @throws \Exception|ProtocolException
     */
    protected function ehlo()
    {
        $host = $this->config['helo'];

        // Support for older, less-compliant remote servers. Tries multiple attempts of EHLO or HELO.
        try
        {
            $this->_send('EHLO ' . $host);
            $this->_expect(250, 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2
        }
        catch (ProtocolException $e)
        {
            $this->_send('HELO ' . $host);
            $this->_expect(250, 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2
        }
    }


    /**
     * Issues MAIL command
     *
     * @param string $from Sender mailbox
     * @throws ProtocolException
     */
    public function mail(string $from)
    {
        if ($this->sess !== true)
            throw new ProtocolException('A valid session has not been started');

        $this->_send('MAIL FROM:<' . $from . '>');
        $this->_expect(250, 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2

        // Set mail to true, clear recipients and any existing data flags as per 4.1.1.2 of RFC 2821
        $this->mail = true;
        $this->rcpt = false;
        $this->data = false;
    }


    /**
     * Issues RCPT command
     *
     * @param  string $to Receiver(s) mailbox
     * @throws RuntimeException
     */
    public function rcpt(string $to)
    {
        if ($this->mail !== true)
            throw new ProtocolException('No sender reverse path has been supplied');

        // Set rcpt to true, as per 4.1.1.3 of RFC 2821
        $this->_send('RCPT TO:<' . $to . '>');
        $this->_expect([250, 251], 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2
        $this->rcpt = true;
    }


    /**
     * Issues DATA command
     *
     * @param string $data
     * @throws ProtocolException
     */
    public function data(string $data)
    {
        // Ensure recipients have been set
        if ($this->rcpt !== true)
            throw new ProtocolException('No recipient forward path has been supplied');

        $this->_send('DATA');
        $this->_expect(354, 120); // Timeout set for 2 minutes as per RFC 2821 4.5.3.2

        if (($fp = fopen("php://temp", "r+")) === false)
            throw new \RuntimeException('cannot fopen');
        if (fwrite($fp, $data) === false)
            throw new \RuntimeException('cannot fwrite');
        unset($data);
        rewind($fp);

        // max line length is 998 char + \r\n = 1000
        while (($line = stream_get_line($fp, 1000, "\n")) !== false)
        {
            $line = rtrim($line, "\r");
            if (isset($line[0]) && $line[0] === '.')
            {
                // Escape lines prefixed with a '.'
                $line = '.' . $line;
            }
            $this->_send($line);
        }
        fclose($fp);

        $this->_send('.');
        $this->_expect(250, 600); // Timeout set for 10 minutes as per RFC 2821 4.5.3.2
        $this->data = true;
    }

    /**
     * Issues the RSET command end validates answer
     *
     * Can be used to restore a clean smtp communication state when a
     * transaction has been cancelled or commencing a new transaction.
     */
    public function rset()
    {
        $this->_send('RSET');
        // MS ESMTP doesn't follow RFC, see [ZF-1377]
        $this->_expect([250, 220]);

        $this->mail = false;
        $this->rcpt = false;
        $this->data = false;
    }

    /**
     * Issues the QUIT command and clears the current session
     */
    public function quit()
    {
        if ($this->sess)
        {
            $this->auth = false;
            $this->_send('QUIT');
            $this->_expect(221, 300); // Timeout set for 5 minutes as per RFC 2821 4.5.3.2
            $this->stopSession();
        }
    }

    /**
     * Default authentication method
     *
     * This default method is implemented by AUTH adapters to properly authenticate to a remote host.
     *
     * @throws Exception\RuntimeException
     */
    public function auth()
    {
        if ($this->auth === true)
            throw new ProtocolException('Already authenticated for this session');

        $auth_type = isset($this->config['auth_type']) ? $this->config['auth_type'] : null;
        switch ($auth_type)
        {
            case "PLAIN":
                return $this->authPlain();
            case "LOGIN":
                return $this->authLogin();
            case "CRAM-MD5":
                return $this->authCRAMMD5();
        }
    }

    /**
     * Authenticate using auth type PLAIN
     */
    protected function authPlain()
    {
        $this->_send('AUTH PLAIN');
        $this->_expect(334);
        $this->_send(base64_encode("\0" . $this->getUsername() . "\0" . $this->getPassword()));
        $this->_expect(235);
        $this->auth = true;
    }

    /**
     * Authenticate using auth type LOGIN
     */
    protected function authLogin()
    {
        $this->_send('AUTH LOGIN');
        $this->_expect(334);
        $this->_send(base64_encode($this->getUsername()));
        $this->_expect(334);
        $this->_send(base64_encode($this->getPassword()));
        $this->_expect(235);
        $this->auth = true;
    }

    /**
     * Authenticate using auth type CRAM-MD5
     */
    protected function authCRAMMD5()
    {
        $this->_send('AUTH CRAM-MD5');
        $challenge = $this->_expect(334);
        $challenge = base64_decode($challenge);
        $digest = $this->hmacMD5($this->getPassword(), $challenge);
        $this->_send(base64_encode($this->getUsername() . ' ' . $digest));
        $this->_expect(235);
        $this->auth = true;
    }

    /**
     * Closes connection
     *
     */
    public function disconnect()
    {
        $this->_disconnect();
    }

    /**
     * Disconnect from remote host and free resource
     */
    protected function _disconnect()
    {
        // Make sure the session gets closed
        $this->quit();
        parent::_disconnect();
    }

    /**
     * Start mail session
     *
     */
    protected function startSession()
    {
        $this->sess = true;
    }

    /**
     * Stop mail session
     *
     */
    protected function stopSession()
    {
        $this->sess = false;
    }

    /**
     * Set value for username
     *
     * @param string $username
     * @return WASP\Mail\Protocol\SMTP Provides fluent interface
     */
    public function setUsername(string $username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set value for password
     *
     * @param  string $password
     * @return WASP\Mail\Protocol\SMTP Provides fluent interface
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string The password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Prepare CRAM-MD5 response to server's ticket
     *
     * @param string $key Challenge key (usually password)
     * @param string $data Challenge data
     * @param int $block Length of blocks (deprecated; unused)
     * @return string
     */
    protected function hmacMD5(string $key, string $data, int $block = 64)
    {
        return hash_hmac('md5', $data, $key, false);
    }
}
