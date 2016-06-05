<?php

/*
 * This file is an addition to Slim HTTP Basic Authentication middleware
 * @see:
 *   https://github.com/tuupola/slim-basic-auth
 */

namespace com\gpioneers\HttpBasicAuthentication;

use \Slim\Middleware\HttpBasicAuthentication\AuthenticatorInterface;

class ArrayHashedPasswordsAuthenticator implements AuthenticatorInterface
{

    /**
     * @var array
     */
    public $options;

    /**
     * ArrayHashedPasswordsAuthenticator constructor.
     * @param array $options optional
     */
    public function __construct($options = null)
    {
        /* Default options. */
        $this->options = array(
            "users" => array()
        );

        if ($options) {
            $this->options = array_merge($this->options, (array)$options);
        }
    }

    /**
     * @param array $arguments
     * @return bool
     */
    public function __invoke(array $arguments)
    {
        $user = $arguments["user"];
        $password = $arguments["password"];
        if (isset($this->options["users"][$user])) {
            return password_verify($password, $this->options["users"][$user]);
        }
        return false;
    }
}
