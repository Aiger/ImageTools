<?php

namespace AigerTeam\ImageTools\Exceptions;

/**
 * Class FileException
 *
 * Исключение, выбрасываемое при ошибке, связанной с работой с файлом.
 *
 * @author Finesse
 * @package AigerTeam\ImageTools\Exceptions
 */
class FileException extends \Exception
{
    protected $path;

    /**
     * {@inheritDoc}
     *
     * @param string|null $path Путь в файловой системе, на котором произошла ошибка
     */
    public function __construct( $message, $path = null, $code = 0, \Exception $previous = null )
    {
        parent::__construct( $message, $code, $previous );
    }

    /**
     * @var string|null Возвращает путь в файловой системе, на котором произошла ошибка
     */
    public function getPath()
    {
        return $this->path;
    }
}