<?php

namespace Yggdrasil\Exceptions;

class ProfileUpdateException extends YggdrasilException
{
    /**
     * Short description of the error.
     *
     * @var string
     */
    protected $error = 'Duplicate profile name found.';

    /**
     * Status code of HTTP response.
     *
     * @var int
     */
    protected $statusCode = 500;
}
