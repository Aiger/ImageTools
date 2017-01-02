<?php

namespace AigerTeam\ImageTools;

use AigerTeam\ImageTools\Exceptions\FileException;
use AigerTeam\ImageTools\Exceptions\ImageFileException;

/**
 * Class ImageFactory
 *
 * Image factory. Creates images by various ways.
 *
 * @version 1.0.7
 * @author Finesse
 * @package AigerTeam\ImageTools
 */
class ImageFactory
{
    /**
     * @param int $memoryLimit Amount of bytes to which memory limit should be extended. Too small amount may cause 
     *  "not enough memory" error while handling images.
     */
    public function __construct( $memoryLimit = 134217728 )
    {
        @ini_set( 'gd.jpeg_ignore_warning', true );

        try {
            $this->extendMemoryLimit( $memoryLimit );
        } catch ( \Exception $e ) {}
    }

    /**
     * Creates blank image.
     *
     * @param int $width Image width (px)
     * @param int $height Image height (px)
     * @param float[]|null $fill Fill color (array with keys `r`, `g`, `b` and optional `a`). If null is provided image 
     *  will be fully transparent.
     * @return Image
     * @throws \Exception
     *
     * @since 1.0.0
     */
	public function blank( $width, $height, Array $fill = null )
    {
        $bitmap = @imagecreatetruecolor( $width, $height );

        if ( !$bitmap )
            throw new \Exception( 'Can\'t create blank image. Perhaps not enough RAM.' );

        $color = Image::allocateColor( $bitmap, $fill );
        $colorParts = imagecolorsforindex( $bitmap, $color );
        $isTransparent = !empty( $colorParts[ 'alpha' ] );

        @imagealphablending( $bitmap, false );
        @imagefill( $bitmap, 0, 0, $color );

		return new Image( $bitmap, $isTransparent );
	}

    /**
     * Reads image from file.
     *
     * @param string $file Path to the image file in the filesystem.
     * @return Image
     * @throws ImageFileException If image can't be retrieved from the file
     * @throws FileException If file can't be read
     * @throws \Exception In case of other error
     *
     * @since 1.0.0
     */
	public function openFile( $file )
    {
        // Clear file info cache
        @clearstatcache( true, $file );

        // Does file exist?
        if ( !file_exists( $file ) || !is_file( $file ))
            throw new FileException( 'Given file doesn\'t exist or not a file.', $file );

        // Is it readable?
        if ( !is_readable( $file ))
            throw new FileException( 'Given file isn\'t readable.', $file );

        // Is it image?
		$size = getimagesize( $file );
		if ( $size === false )
            throw new ImageFileException( 'Given file isn\'t an image.', $file );

        // Retrieve image type
		$format = strtolower( substr( $size['mime'], strpos( $size['mime'], '/' ) + 1 ) );
		if ($format === 'x-ms-bmp')
			$format = 'wbmp';

        // Does function that opens this type exist?
		$func = 'imagecreatefrom' . $format;
		if ( !function_exists( $func ) )
            throw new ImageFileException( 'Unknown image type.', $file );

        // Open image file
        $bitmap = @$func( $file );
		if ( !$bitmap )
            throw new ImageFileException( 'Can\'t open image file. Perhaps not enough RAM.', $file );

        // Create image object
        $image = new Image( $bitmap, in_array( $format, Array( 'png', 'gif' ) ) );

        // Rotate non-default oriended JPEG
		if (
            function_exists( 'exif_read_data' ) &&
            is_array( $exif = @exif_read_data( $file, 0, true )) &&
            isset( $exif[ 'IFD0' ][ 'Orientation' ])
        ) {
			switch( $exif[ 'IFD0' ][ 'Orientation' ]) {
				case 8: $image = $image->rotate(  90 ); break;
				case 3: $image = $image->rotate( 180 ); break;
				case 6: $image = $image->rotate( -90 ); break;
			}
		}

		return $image;
	}

    /**
     * Craetes image from screenshot.
     *
     * Works only on windows (due to http://php.net/manual/en/function.imagegrabscreen.php#refsect1-function.imagegrabscreen-notes).
     *
     * @return Image
     * @throws \Exception
     *
     * @since 1.0.5
     */
    public function screenshot()
    {
        if ( !function_exists( 'imagegrabscreen' ) )
            throw new \Exception( 'Current PHP assembly can\'t take screenshots.' );

        $bitmap = @imagegrabscreen();
        if ( !$bitmap )
            throw new \Exception( 'Can\'t take screenshot due to an unknown reason.' );

        return new Image( $bitmap );
    }

    /**
     * Extend system memory limit to the given value (if current limit is lower).
     *
     * @param int $minSize Amount of bytes
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
                    $curSize = $base * pow( 1024, 5 );  // Someday this will also be not enough
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
