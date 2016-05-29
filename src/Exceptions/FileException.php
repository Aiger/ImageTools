<?php

namespace AigerTeam\ImageTools\Exceptions;

/**
 * Class FileException
 *
 * Thrown in case of a file error.
 *
 * @author Finesse
 * @package AigerTeam\ImageTools\Exceptions
 */
class FileException extends \Exception
{
    /**
     * Path to the file in the filesystem on which error has occured
     */
    protected $path;

    /**
     * {@inheritDoc}
     *
     * @param string|null $path Path to the file in the filesystem on which error has occured
     */
    public function __construct( $message, $path = null, $code = 0, \Exception $previous = null )
    {
        $this->path = $path;
        parent::__construct( $message, $code, $previous );
    }

    /**
     * @return string|null Path to the file in the filesystem on which error has occured (if provided)
     */
    public function getPath()
    {
        return $this->path;
    }
}
