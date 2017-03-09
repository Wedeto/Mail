<?php
/**
 * This is part of WASP, the Web Application Software Platform.
 * This class is adapted from Zend/Mail/Address.
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

use WASP\Mail\Mime\Mime;

class Address 
{
    protected $email;
    protected $name;

    /**
     * Create the addres instance
     *
     * @param string $email The e-mail address
     * @param string $name The name of the recipient
     * @throws InvalidArgumentException when the e-mail address is not valid
     */
    public function __construct(string $email, string $name)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        if ($email === false)
            throw new \InvalidArgumentException('Email must be a valid email address');

        $this->email = $email;
        $this->name = preg_replace('/\p{Cc}/u', '', $name);
    }

    /**
     * @return string The e-mail address
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string The name
     */
    public function getName()
    {
        return empty($this->name) ? null : $this->name;
    }
    
    /**
     * @return string String representation of address
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string String representation of address
     */
    public function toString()
    {
        // E-mail address part
        $email = $this->getEmail();

        if (preg_match('/^(.+)@([^@]+)$/', $email, $matches))
        {
            $localPart = $matches[1];
            $hostname = \idn_to_ascii($matches[2]);
            $email = sprintf('%s@%s', $localPart, $hostname);
        }

        // Name part
        $name = $this->getName();
        if (empty($name))
            return $email;

        if (strpos($name, ',') !== false)
            $name = sprintf('"%s"', str_replace('"', '\\"', $name));

        if (!Mime::isPrintable($name))
            $name = HeaderWrap::mimeEncodeValue($name, 'UTF-8');

        return sprintf('%s <%s>', $name, $email);
    }
}
