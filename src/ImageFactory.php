<?php

namespace AigerTeam\ImageTools;

use AigerTeam\ImageTools\Exceptions\FileException;
use AigerTeam\ImageTools\Exceptions\ImageFileException;

/**
 * Class ImageFactory
 *
 * Фабрика для создания объектов изображения.
 *
 * @author Сергей
 * @package AigerTeam\ImageTools
 */
class ImageFactory
{
    /**
     * @param int $memoryLimit Количество байт, до которых нужно расширить ограничение на количество используемой
     * оперативной памяти.
     */
    public function __construct( $memoryLimit = 134217728 )
    {
        @ini_set( 'gd.jpeg_ignore_warning', true );

        try {
            $this->extendMemoryLimit( $memoryLimit );
        } catch ( \Exception $e ) {}
    }

    /**
     * Создаёт пустое изображение указанного размера.
     *
     * @param int $width Высота изображения в пикселях
     * @param int $height Ширина изображения в пикселях
     * @param float[]|null $fill Цвет заливки изображения (массив с индексами r, g, b и по желанию a). Если указать null,
     * то изображение будет полностью прозрачным.
     * @return Image
     * @throws \Exception В случае непредвиденной ошибки
     */
	public function blank( $width, $height, Array $fill = null )
    {
        $bitmap = @imagecreatetruecolor( $width, $height );

        if ( !$bitmap )
            throw new \Exception( 'Не удалось создать изображение. Возможно, не хватает оперативной памяти.' );

        $color = Image::allocateColor( $bitmap, $fill );
        $colorParts = imagecolorsforindex( $bitmap, $color );
        $isTransparent = !empty( $colorParts[ 'alpha' ] );

        @imagealphablending( $bitmap, false );
        @imagefill( $bitmap, 0, 0, $color );

		return new Image( $bitmap, $isTransparent );
	}


    /**
     * Создаёт объект изображения, читая его из файла изображения.
     *
     * @param string $file Путь к файлу изображения на сервере
     * @return Image
     * @throws ImageFileException Если не удалось прочитать изображение из файла
     * @throws FileException Если не удалось открыть файл изображения
     * @throws \Exception В случае непредвиденной ошибки
     */
	public function openFile( $file )
    {
        // Очистка кеша информации о файле
        try {
            clearstatcache( true, $file );
        } catch ( \Exception $e ) {
            throw new FileException( 'Не удалось очистить кэш информации о файле. Ошибка: ' . $e->getMessage(), $file );
        }

        // Проверка, существует ли файл
        if ( !file_exists( $file ) || !is_file( $file ) )
            throw new FileException( 'Файл не существует или не является файлом.', $file );

        // Проверка файла на возможность чтения
        if ( !is_readable( $file ) )
            throw new FileException( 'Файл недоступен для чтения.', $file );

        // Является ли файл изображением
		$size = getimagesize( $file );
		if( $size === false )
            throw new ImageFileException( 'Файл не является изображением.', $file );

        // Определение формата изображения
		$format = strtolower( substr( $size['mime'], strpos( $size['mime'], '/' ) + 1 ) );
		if($format === 'x-ms-bmp')
			$format = 'wbmp';

        // Есть ли функция, которая может открыть изображение
		$func = 'imagecreatefrom' . $format;
		if( !function_exists( $func ) )
            throw new ImageFileException( 'Нет подходящей функции для открытия изображения.', $file );

        // Открытие изображения
        $bitmap = @$func( $file );
		if( !$bitmap )
            throw new ImageFileException( 'Не удалось открыть изображение. Возможно, не хватает оперативной памяти.', $file );

        // Создание объекта изображения
        $image = new Image( $bitmap, in_array( $format, Array( 'png', 'gif' ) ) );

        // Поворачивание на место повёрнутых JPEG-ов
		if(
            function_exists( 'exif_read_data' ) &&
            is_array( $exif = @exif_read_data( $file, 0, true ) ) &&
            isset( $exif[ 'IFD0' ][ 'Orientation' ] )
        ) {
			switch( $exif[ 'IFD0' ][ 'Orientation' ] ) {
				case 8: $image = $image->rotate(  90 ); break;
				case 3: $image = $image->rotate( 180 ); break;
				case 6: $image = $image->rotate( -90 ); break;
			}
		}

		return $image;
	}


    /**
     * Расширяет ограничение на количество используемой скритом оперативной памяти сервера до установленного значения.
     *
     * @param int $minSize Количество байт
     */
    protected function extendMemoryLimit( $minSize )
    {
        $curSize = ini_get( 'memory_limit' );

        if ( !is_numeric( $curSize ) ) {
            $base = substr( $curSize, 0, -1 );
            $power = strtolower( substr( $curSize, -1 ) );

            switch ( $power ) {
                case 'k':
                    $curSize = $base * 1024;            // 640K ought to be enough for anybody
                    break;
                case 'm':
                    $curSize = $base * pow( 1024, 2 );
                    break;
                case 'g':
                    $curSize = $base * pow( 1024, 3 );
                    break;
                case 't':
                    $curSize = $base * pow( 1024, 4 );
                    break;
                case 'p':
                    $curSize = $base * pow( 1024, 5 );  // Sometime this will also be not enough
                    break;
                default:
                    $curSize = 0;
            }
        } else {
            $curSize = (int)$curSize;
        }

        if ( $minSize > $curSize ) {
            ini_set( 'memory_limit', $minSize );
        }
    }
}