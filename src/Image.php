<?php

namespace AigerTeam\ImageTools;

use AigerTeam\ImageTools\Exceptions\FileException;

if ( !defined( 'IMAGETYPE_WEBP' )) define( 'IMAGETYPE_WEBP', 117 );

/**
 * Class Image
 *
 * Object-oriented wrap for DG image that provides simple methods for image processing. This class uses immutable
 * object style. It means that the image object can't be changed after creation, the result of any modifying method of
 * an image object is the new image object.
 *
 * A class instance can be created by:
 *  - passing DG resource to the class constructor;
 *  - calling a method of an ImageFactory object.
 *
 * @version 1.0.7
 * @author Finesse
 * @author maindefine
 * @package AigerTeam\ImageTools
 */
class Image
{
    /**
     * Image resize mode. Image aspect ratio is preserved and image is not clipped.
     *
     * @since 1.0.0
     */
    const SIZING_CONTAIN = 'contain';

    /**
     * Image resize mode. Image aspect ratio is preserved and image is stretched to cover the whole area. Image may be
     * clipped.
     *
     * @since 1.0.0
     */
    const SIZING_COVER = 'cover';

    /**
     * Image resize mode. Image is stretched to cover the whole area and isn't clipped. Image accept ratio may be
     * changed.
     *
     * @since 1.0.0
     */
    const SIZING_EXEC = 'exec';

    /**
     * @var resource Image DG resource. It is assumed that:
     *   - imagealphablending options is always set to true;
     *   - Color mode is always True Color.
     */
    protected $bitmap;

    /**
     * @var bool Whether image has transparent pixels
     */
    protected $isTransparent = false;

    /**
     * @param resource $bitmap Image DG resource. It becomes this object own. So the resource can be modified here. It
     *  will be automatically destroyed on this object destruction.
     * @param bool $isTransparent Whether image resource has transparent pixels. Not significant argument, it's used
     *  only when output file format is selected.
     * @throws \InvalidArgumentException If given resource is not a DG resource
     * @throws \Exception
     */
    public function __construct( $bitmap, $isTransparent = false )
    {
        if ( !is_resource( $bitmap ) )
            throw new \InvalidArgumentException( 'Argument $bitmap expected to be resource, ' . gettype( $bitmap ) . 'given.' );

        if ( get_resource_type( $bitmap ) !== 'gd' )
            throw new \InvalidArgumentException( 'The resource from the $bitmap argument is not image resource.' );

        if ( !imageistruecolor( $bitmap ) ) {
            if ( !@imagepalettetotruecolor( $bitmap ) )
                throw new \Exception( 'Can\'t transform image to True Color. Perhaps not enough RAM.' );
        }

        @imagealphablending( $bitmap, true );

        $this->bitmap = $bitmap;
        $this->isTransparent = !!$isTransparent;
    }

    /**
     * @throws \Exception
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        $this->bitmap = static::copyBitmap( $this->bitmap );
        @imagealphablending( $this->bitmap, true );
    }

    /**
     * @since 1.0.0
     */
    public function __destruct()
    {
        @imagedestroy( $this->bitmap );
    }

    /**
     * Returns image width (px)
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getWidth()
    {
        return imagesx( $this->bitmap );
    }

    /**
     * Returns image height (px)
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getHeight()
    {
        return imagesy( $this->bitmap );
    }

    /**
     * Determines recommended file type fo this image.
     *
     * @return int One of global constants IMAGETYPE_*
     *
     * @since 1.0.2
     */
    public function getRecommendedType()
    {
        if ( $this->isTransparent )
            return IMAGETYPE_PNG;

        return IMAGETYPE_JPEG;
    }

    /**
     * Determines recommended file extension for this image.
     *
     * @param bool $includeDot Include dot to the output extension
     * @return string Extension string that can be added to the file name
     *
     * @since 1.0.2
     */
    public function getRecommendedExtension( $includeDot = false )
    {
        return static::imageType2extension( $this->getRecommendedType(), $includeDot );
    }

    /**
     * Changes this image size.
     *
     * @param int|null $width Desired image width (px). If null, will be calculated from height saving aspect ratio.
     *  It's required to be provided at least one of width and height.
     * @param int|null $height Desired image height (px). If null, will be calculated from width saving aspect ratio.
     * @param bool $allowIncrease Should image width or height be allowed to increase. If no, image size will be
     *  decreased or not modified. In any case, all other options will be met.
     * @param string $sizing Determines how image will be scaled to fit given area. Works only if both width and height
     *  are set. See SIZING_* constants of this class to get possible variants.
     * @param float $alignHor Image horizontal position in case when image is clipped. 0 — left edge is visible, 1 —
     *  right edge is visible.
     * @param float $alignVer Image vertical position in case when image is clipped. 0 — top edge is visible, 1 —
     *  bottom edge is visible.
     * @return static
     * @throws \Exception
     *
     * @see Image::SIZING_CONTAIN
     * @see Image::SIZING_COVER
     * @see Image::SIZING_EXEC
     *
     * @since 1.0.0
     */
    function resize(
        $width = null,
        $height = null,
        $allowIncrease = false,
        $sizing = self::SIZING_CONTAIN,
        $alignHor = 0.5,
        $alignVer = 0.5
    ) {
        // Check input data

        if ( is_null( $width ) && is_null( $height ) )
            throw new \InvalidArgumentException( 'Neither width nor height is provided.' );

        if ( !is_null( $width ) ) {
            if ( !is_numeric( $width ) )
                throw new \InvalidArgumentException( 'Argument $width expected to be number or null, ' . gettype( $width ) . ' given.' );

            $width = (int)max( $width, 1 );
        }

        if ( !is_null( $height ) ) {
            if ( !is_numeric( $height ) )
                throw new \InvalidArgumentException( 'Argument $height expected to be number or null, ' . gettype( $height ) . ' given.' );

            $height = (int)max( $height, 1 );
        }


        // Calculate new image parameters
        $params = static::getResizeParameters(
            $this->getWidth(),
            $this->getHeight(),
            $width,
            $height,
            $allowIncrease,
            $sizing,
            $alignHor,
            $alignVer
        );

        if ( !$params )
            return $this;


        // Scaling...
        $bitmap = @imagecreatetruecolor( $params[ 'dstWidth' ], $params[ 'dstHeight' ] );
        @imagealphablending( $bitmap, false );
        $result = @imagecopyresampled(
            $bitmap,
            $this->bitmap,
            0,                     0,
            $params[ 'srcX' ],     $params[ 'srcY' ],
            $params[ 'dstWidth' ], $params[ 'dstHeight' ],
            $params[ 'srcWidth' ], $params[ 'srcHeight' ]
        );

        if ( !$result )
            throw new \Exception( 'Can\'t resize image due to an unknown reason.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }

    /**
     * Puts stamp or watermark to this image. Stamp aspect ratio is preserved.
     *
     * @param self $image Stamp of watermark image
     * @param float $size Stamp size relative to image size subtract padding. From 0 (0х0 size), to 1 (the whole area).
     * @param float $posX Horizontal stamp position considering padding. From 0 (left edge) to 1 (right edge).
     * @param float $posY Vertical stamp position considering padding. From 0 (top edge) to 1 (bottom edge).
     * @param float $padding Stamp padding from this image edges. The padding is calculated this way: the value of this
     *  argument is multiplied to the image diagonal length.
     * @param float $opacity Stamp opacity. 0 — fully transparent, 1 — fully opaque.
     * @param bool $allowIncrease Is stamp size allowed to be increased
     * @return static
     * @throws \Exception
     *
     * @since 1.0.5
     */
    public function stamp(
        self $image,
        $size = 0.15,
        $posX = 1.0,
        $posY = 1.0,
        $padding = 0.02,
        $opacity = 1.0,
        $allowIncrease = true
    ) {
        $dstWidth  = $this->getWidth();
        $dstHeight = $this->getHeight();
        $srcWidth  = $image->getWidth();
        $srcHeight = $image->getHeight();
        $paddingPX = round( sqrt( $dstWidth * $dstWidth + $dstHeight * $dstHeight ) * $padding );
        $dstBoxWidth  = $dstWidth  - $paddingPX * 2;
        $dstBoxHeight = $dstHeight - $paddingPX * 2;

        if ( $dstBoxWidth / $dstBoxHeight > $srcWidth / $srcHeight ) {
            $scale = $dstBoxHeight * $size / $srcHeight;
        } else {
            $scale = $dstBoxWidth * $size / $srcWidth;
        }

        if ( !$allowIncrease && $scale > 1 )
            $scale = 1;

        $stampWidth  = round( $srcWidth  * $scale );
        $stampHeight = round( $srcHeight * $scale );

        return $this->insertImage(
            $image,
            round( $paddingPX + ( $dstBoxWidth  - $stampWidth  ) * $posX ),
            round( $paddingPX + ( $dstBoxHeight - $stampHeight ) * $posY ),
            $stampWidth,
            $stampHeight,
            $opacity
        );
    }

    /**
     * Prints text on this image.
     *
     * @param string $text Text to print
     * @param string $font Text font. Path to the .ttf font file on the server.
     * @param float $fontSize Font size (px)
     * @param int $x X-coordinate of the text block
     * @param int $y Y-coordinate position of the text baseline
     * @param float[] $color Text color (array with keys `r`, `g`, `b` and optional `a`)
     * @param float $alignHor Text align relative to the X-position. 0 — to the right, 1 — to the left.
     * @param float $angle Text block rotation angle (degrees, counterclock-wise). Text is rotated around the beginning
     *  point of the baseline.
     * @return static
     * @throws \Exception
     *
     * @since 1.0.0
     */
    function write(
        $text,
        $font,
        $fontSize = 12.0,
        $x = 0,
        $y = 0,
        Array $color = Array( 'r' => 0, 'g' => 0, 'b' => 0 ),
        $alignHor = .0,
        $angle = .0
    ) {
        try {
            $coord = @imagettfbbox( $fontSize, $angle, $font, $text );
            if ( $coord === false )
                throw new \Exception( 'Can\'t retrieve text block size.' );

            $width  = $coord[ 2 ] - $coord[ 0 ];
            $x -= $width * $alignHor;
            $color = static::allocateColor( $this->bitmap, $color );
            $bitmap = static::copyBitmap( $this->bitmap );
            @imagealphablending( $bitmap, true );

            $result = @imagettftext( $bitmap, $fontSize, $angle, $x, $y, $color, $font, $text );
            if ( !$result )
                throw new \Exception;

        } catch ( \Exception $e ) {
            throw new \Exception( 'Can\'t print text on the image due to an unknown reason.' );
        }

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }

    /**
     * Rotates this image.
     *
     * @param float $angle Angle (degrees, counterclock-wise)
     * @param float[]|null $underlay Background color (array with keys `r`, `g`, `b` and optional `a`). Null means fully
     *  transparent color. Background appears if given angle is not multiple 90°.
     * @throws \Exception
     *
     * @since 1.0.0
     */
    function rotate( $angle, Array $underlay = null )
    {
        if ( $angle == 0 )
            return $this;

        $bitmap = @imagerotate( $this->bitmap, $angle, static::allocateColor( $this->bitmap, $underlay ) );

        if ( !$bitmap )
            throw new \Exception( 'Can\'t rotate image due to an unknown reason.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }

    /**
     * Makes this image transparent.
     *
     * @param float $opacity Opacity. 0 — fully transparent, 1 — fully opaque, more then 1 — transparency of semi-opaque
     *  pixels will be decreased.
     * @return static
     * @throws \InvalidArgumentException
     *
     * @since 1.0.4
     */
    function setOpacity( $opacity )
    {
        if ( !is_numeric( $opacity )) {
            throw new \InvalidArgumentException( 'Argument $opacity expected to be number, ' . gettype( $opacity ) . ' given.' );
        }

        $opacity = max( 0, $opacity );

        if ( $opacity == 1 ) {
            return $this;
        }

        $restLayersOpacity = ceil( $opacity ) - 1;
        $firstLayerOpacity = $opacity - $restLayersOpacity;

        $bitmap = static::copyBitmap( $this->bitmap );
        $transparency = 1 - $firstLayerOpacity;
        imagefilter( $bitmap, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * $transparency );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = true;

        for ( $i = 0; $i < $restLayersOpacity; ++$i ) {
            $newImage = $newImage->insertImage( $this );
        }

        return $newImage;
    }

    /**
     * Insert other image into this image.
     *
     * @param self $image Inserted image
     * @param int $dstX X-position of the inserted image on this image (px)
     * @param int $dstY Y-position of the inserted image on this image (px)
     * @param int|null $dstWidth New width of the taken area from the inserted image. If null, will be the same as
     *  $srcWidth.
     * @param int|null $dstHeight New height of the taken area from the inserted image. If null, will be the same as
     *  $srcHeight.
     * @param float $opacity Opacity of the inserted image. 0 — fully transparent, 1 — fully opaque.
     * @param int $srcX X-position of the taken area from the inserted image. Default 0.
     * @param int $srcY Y-position of the taken area from the inserted image. Default 0.
     * @param int|null $srcWidth Width of the taken area of the inserted image. If null, the full width is taken.
     * @param int|null $srcHeight Height of the taken area of the inserted image. If null, the full height is taken.
     * @return static
     * @throws \Exception
     *
     * @since 1.0.0
     */
    function insertImage(
        self $image,
        $dstX = 0,
        $dstY = 0,
        $dstWidth  = null,
        $dstHeight = null,
        $opacity = 1.0,
        $srcX = 0,
        $srcY = 0,
        $srcWidth  = null,
        $srcHeight = null
    ) {
        if ( !is_numeric( $dstX ) ) $dstX = 0;
        if ( !is_numeric( $dstY ) ) $dstY = 0;
        if ( !is_numeric( $srcX ) ) $srcX = 0;
        if ( !is_numeric( $srcY ) ) $srcY = 0;
        if ( !is_numeric( $srcWidth  ) ) $srcWidth  = $image->getWidth() - $srcX;
        if ( !is_numeric( $srcHeight ) ) $srcHeight = $image->getHeight() - $srcY;
        if ( !is_numeric( $dstWidth  ) ) $dstWidth  = $srcWidth;
        if ( !is_numeric( $dstHeight ) ) $dstHeight = $srcHeight;
        if ( !is_numeric( $opacity ) ) $opacity = 1;

        if ( $opacity <= 0 )
            return $this;

        if ( $opacity != 1 ) {
            if (
                $srcX !== 0 || $srcY !== 0 ||
                $srcWidth !== $image->getWidth() || $srcHeight !== $image->getHeight()
            ) {
                $image = $image->crop( $srcX, $srcY, $srcWidth, $srcHeight );
                $srcX  = 0;
                $srcY  = 0;
            }

            // The setOpacity method is expensive, so we have to use it on the inserted image when it has the minimum
            // size. Here, before setting opacity, we find out if it has less pixels after resizing and resize it if
            // it does.
            if ( $srcWidth * $srcHeight > $dstWidth * $dstHeight ) {
                $image     = $image->resize( $dstWidth, $dstHeight, true, static::SIZING_EXEC );
                $srcWidth  = $dstWidth;
                $srcHeight = $dstHeight;
            }

            $image = $image->setOpacity( $opacity );
        }

        $bitmap = static::copyBitmap( $this->bitmap );
        @imagealphablending( $bitmap, true );

        if ( $srcWidth === $dstWidth && $srcHeight === $dstHeight ) {
            $result = @imagecopy(
                $bitmap,
                $image->bitmap,
                $dstX,     $dstY,
                $srcX,     $srcY,
                $srcWidth, $srcHeight
            );
        } else {
            $result = @imagecopyresampled(
                $bitmap,
                $image->bitmap,
                $dstX,     $dstY,
                $srcX,     $srcY,
                $dstWidth, $dstHeight,
                $srcWidth, $srcHeight
            );
        }

        if ( !$result )
            throw new \Exception( 'Can\'t insert image due to an unknown reason.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }

    /**
     * Clips this image.
     *
     * @param int $x X-position of the left edge of the clipping area
     * @param int $y Y-position of the top edge of the clipping area
     * @param int|null $width Width of the clipping area. If null, the whole width will be used.
     * @param int|null $height Height of the clipping area. If null, the whole height will be used.
     * @return static
     * @throws \Exception
     *
     * @since 1.0.4
     */
    function crop( $x = 0, $y = 0, $width = null, $height = null )
    {
        if ( !is_numeric( $x ) ) $x = 0;
        if ( !is_numeric( $y ) ) $y = 0;
        if ( !is_numeric( $width ) )  $width  = $this->getWidth()  - $x;
        if ( !is_numeric( $height ) ) $height = $this->getHeight() - $y;

        $bitmap = @imagecreatetruecolor( $width, $height );
        @imagealphablending( $bitmap, false );
        $result = @imagecopy( $bitmap, $this->bitmap, 0, 0, $x, $y, $width, $height );

        if ( !$bitmap || !$result )
            throw new \Exception( 'Can\'t crop image due to an unknown reason.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }

    /**
     * Finds dominant colors on this image. Uses K-means clustering algorithm.
     *
     * @param int $amount Amount of colors to find
     * @param bool $sort Sort found colors by popularity
     * @param int $maxPreSize Size to which image is decreased before starting algorithm. The lower value the faster
     *  search and the less accurate result.
     * @param int $epsilon Error level on which search should be stopped. The lower error the slower search and the more
     *  accurate result.
     * @return int[][] Array of colors. Color is an array with keys `r`, `g` and `b`.
     * @throws \Exception
     *
     * @since 1.0.0
     */
    public function getKMeanColors( $amount = 1, $sort = true, $maxPreSize = 30, $epsilon = 2 )
    {
        $eps    = $epsilon;
        $image  = $this->resize( $maxPreSize, $maxPreSize, false, static::SIZING_EXEC );
        $width  = $image->getWidth();
        $height = $image->getHeight();

        $pixelsAmount = $width * $height;
        $pixels = Array();
        for ( $i = 0; $i < $width; ++$i )
            for ( $j = 0; $j < $height; ++$j ) {
                $rgb = imagecolorat( $image->bitmap, $i, $j );
                $pixels[] = Array(
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8)  & 0xFF,
                     $rgb        & 0xFF
                );
            }

        unset( $image );

        $clusters = Array();
        $pixelsChosen = Array();
        for ( $i = 0; $i < $amount; ++$i ) {
            do {
                $id = rand( 0, $pixelsAmount - 1 );
            } while( in_array( $id, $pixelsChosen ));
            $pixelsChosen[] = $id;
            $clusters[] = $pixels[ $id ];
        }

        $clustersPixels = Array();
        $clustersAmounts = Array();
        do {
            for ( $i = 0; $i < $amount; ++$i ) {
                $clustersPixels[ $i ] = Array();
                $clustersAmounts[ $i ] = 0;
            }

            for ( $i = 0; $i < $pixelsAmount; ++$i ) {
                $distMin = -1;
                $id = 0;
                for ( $j = 0; $j < $amount; ++$j ) {
                    $dist = static::sqDistance( $pixels[ $i ], $clusters[ $j ], 3 );
                    if ( $distMin == -1 or $dist < $distMin ) {
                        $distMin = $dist;
                        $id = $j;
                    }
                }
                $clustersPixels[ $id ][] = $i;
                ++$clustersAmounts[$id];
            }

            $diff = 0;
            for( $i = 0; $i < $amount; ++$i ) {
                if( $clustersAmounts[ $i ] > 0 ) {
                    $old = $clusters[ $i ];
                    for ( $k = 0; $k < 3; ++$k ) {
                        $clusters[ $i ][ $k ] = 0;
                        for( $j = 0; $j < $clustersAmounts[ $i ]; ++$j )
                            $clusters[ $i ][ $k ] += $pixels[ $clustersPixels[ $i ][ $j ] ][ $k ];
                        $clusters[ $i ][ $k ] /= $clustersAmounts[ $i ];
                    }
                    $dist = static::sqDistance( $old, $clusters[ $i ], 3 );
                    $diff = max( $diff, $dist );
                }
            }
        } while( $diff >= $eps );

        if ( $sort and $amount > 1 )
            for ( $i = 1; $i < $amount; ++$i )
                for( $j = $i; $j >= 1 && $clustersAmounts[ $j ] > $clustersAmounts[ $j - 1 ]; --$j ) {
                    $t = $clustersAmounts[ $j - 1 ];
                    $clustersAmounts[ $j - 1 ] = $clustersAmounts[ $j ];
                    $clustersAmounts[ $j ] = $t;

                    $t = $clusters[ $j - 1 ];
                    $clusters[ $j - 1 ] = $clusters[ $j ];
                    $clusters[ $j ] = $t;
                }

        for ( $i = 0; $i < $amount; ++$i )
            for ( $j = 0; $j < 3; ++$j )
                $clusters[ $i ][ $j ] = floor( $clusters[ $i ][ $j ] );

        $ret = Array();
        for ( $i = 0; $i < $amount; ++$i )
            $ret[] = Array(
                'r' => $clusters[ $i ][ 0 ],
                'g' => $clusters[ $i ][ 1 ],
                'b' => $clusters[ $i ][ 2 ]
            );

        return $ret;
    }

    /**
     * Saves this image to one of the formats and prints it's byte code to the main output. Can be used to send image to
     * a browser as a HTTP response.
     *
     * @param int|null $type Image format to encode. Value is one of global constants IMAGETYPE_*. If null, format will
     *  be determined automatically due to recommended format for this image.
     * @param float|null $quality Save quality. 0 — small size, 1 — good quality.
     * @return static Itself
     * @throws \InvalidArgumentException If given type isn't supported
     * @throws \Exception
     *
     * @since 1.0.0
     */
    public function display( $type = null, $quality = null )
    {
        if ( empty( $type ) )
            $type = $this->getRecommendedType();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        if ( !static::saveImage( $this->bitmap, null, $type, $quality ) )
            throw new \Exception( 'Can\'t save file due to an unknown reason.' );

        return $this;
    }

    /**
     * Saves this image to the given file.
     *
     * @param string $file Path to the file in the filesystem
     * @param int|null $type Image format to encode. Value is one of global constants IMAGETYPE_*. If null, format will
     *  be determined automatically due to recommended format for this image.
     * @param float|null $quality Save quality. 0 — small size, 1 — good quality.
     * @return static Itself
     * @throws FileException If given path is not available for writing
     * @throws \InvalidArgumentException If given type isn't supported
     * @throws \Exception
     *
     * @since 1.0.0
     */
    public function toFile( $file, $type = null, $quality = null )
    {
        // Clear file info cache
        @clearstatcache( true, $file );

        // Is file available for writing? Check and prepare directory for file.
        if ( file_exists( $file ) )
        {
            if ( !is_file( $file ) )
                throw new FileException( 'Given path is not a file.', $file );

            if ( !is_writable( $file ) )
                throw new FileException( 'Given file is not available for writing.', $file );
        }
        else
        {
            $dir = dirname( $file );

            if ( file_exists( $dir ) )
            {
                if ( !is_dir( $dir ) )
                    throw new FileException( 'Can\'t write to the given path because file directory `' . $dir . ' is not a directory`.' );

                if ( !is_writable( $dir ) )
                    throw new FileException( 'Given path is not available for writing.', $file );
            }
            else
            {
                if ( !@mkdir( $dir, 0777, true ) )
                    throw new FileException( 'Given path is not available for writing.', $file );
            }
        }

        // Check arguments
        if ( !is_numeric( $quality ) )
            $quality = 1;

        // Determine format and save image
        $saved = false;
        $result = null;

        if ( empty( $type ) )
        {
            $extension = pathinfo( $file, PATHINFO_EXTENSION );

            try {
                $type = static::extension2imageType( $extension );
                $result = static::saveImage( $this->bitmap, $file, $type, $quality );
                $saved = true;
            } catch ( \InvalidArgumentException $e ) {}

            if ( !$saved )
                $type = $this->getRecommendedType();
        }

        if ( !$saved )
            $result = static::saveImage( $this->bitmap, $file, $type, $quality );

        if ( !$result )
            throw new \Exception( 'Can\'t encode image to the given format.' );

        @clearstatcache( true, $file );

        return $this;
    }

    /**
     * Saves file to the given directory. File name is set automatically according to the given arguments.
     *
     * @param string $dir Path to the directory in which image should be saved
     * @param string $name Desired file name (without extension)
     * @param bool $rewrite Allow to rewrite existing files? If false is set file name will be chosen so as not to
     *  coincide with the existing file.
     * @param int|null $type Image format to encode. Value is one of global constants IMAGETYPE_*. If null, format will
     *  be determined automatically due to recommended format for this image.
     * @param float|null $quality Save quality. 0 — small size, 1 — good quality.
     * @return string Path of the saved file in the file system
     * @throws FileException If given path is not available for writing
     * @throws \InvalidArgumentException If given type isn't supported
     * @throws \Exception
     *
     * @since 1.0.2
     */
    public function toUncertainFile( $dir, $name = 'image', $rewrite = false, $type = null, $quality = 1.0 )
    {
        if ( empty( $type ) )
            $type = $this->getRecommendedType();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        $extension = static::imageType2extension( $type, true );
        $file = $dir . DIRECTORY_SEPARATOR . $name . $extension;
        $counter = 0;

        while ( file_exists( $file ) && ( !$rewrite || $rewrite && !is_file( $file ) ) )
            $file = $dir . DIRECTORY_SEPARATOR . $name . '('. ++$counter .')' . $extension;

        $this->toFile( $file, $type, $quality );

        return $file;
    }

    /**
     * Creates DG resource based on this image.
     *
     * @return resource DG resource. It is allowed to be modified, this object won't change.
     * @throws \Exception
     *
     * @since 1.0.1
     */
    public function toResource()
    {
        return static::copyBitmap( $this->bitmap );
    }

    /**
     * Generates DG color value for given human-friendly color.
     *
     * @param resource $bitmap DG resource for which color should be allocated
     * @param float[]|null $color Color (array with keys `r`, `g`, `b` and optional `a`). Null means fully transparent
     *  color. Background appears if given angle is not multiple 90°.
     * @return int
     * @throws \InvalidArgumentException If given resource is not a DG resource
     *
     * @since 1.0.0
     */
    public static function allocateColor( $bitmap, Array $color = null )
    {
        if ( !is_resource( $bitmap ) )
            throw new \InvalidArgumentException( 'Argument $bitmap expected to be resource, ' . gettype( $bitmap ) . 'given.' );

        if ( get_resource_type( $bitmap ) !== 'gd' )
            throw new \InvalidArgumentException( 'The resource from the $bitmap argument is not image resource.' );

        return is_null( $color )
            ? imagecolorallocatealpha( $bitmap, 0, 0, 0, 127 )
            : imagecolorallocatealpha(
                $bitmap,
                empty( $color[ 'r' ] ) ? 0 : round( $color[ 'r' ] ),
                empty( $color[ 'g' ] ) ? 0 : round( $color[ 'g' ] ),
                empty( $color[ 'b' ] ) ? 0 : round( $color[ 'b' ] ),
                empty( $color[ 'a' ] ) ? 0 : round( ( 1 - $color[ 'a' ] ) * 127 )
            );
    }

    /**
     * Creates instance of current class. This method is required for child class constructor to have any arguments.
     *
     * @param resource $bitmap Image DG resource
     * @return static
     * @throws \InvalidArgumentException If given resource is not a DG resource
     */
    protected static function construct( $bitmap )
    {
        return new static( $bitmap );
    }

    /**
     * Calculates image resizing parameters.
     *
     * @see Image::resize To get description of the other arguments
     *
     * @param int $curWidth Current image width
     * @param int $curHeight Current image height
     * @return int[]|null Parameters array. If null, there is no need to resize. Array keys:
     *  - srcX — X-position of taken area from the source image;
     *  - srcY — Y-position of taken area from the source image;
     *  - srcWidth Width of taken area of the source image;
     *  - srcHeight Height of taken area of the source image;
     *  - dstWidth - New width of the taken area;
     *  - dstHeight - New height of the taken area.
     */
    protected static function getResizeParameters(
        $curWidth,
        $curHeight,
        $desWidth = null,
        $desHeight = null,
        $allowIncrease = false,
        $sizing = self::SIZING_CONTAIN,
        $alignHor = 0.5,
        $alignVer = 0.5
    ) {
        /*
         * des (desired)     — desired size
         * cur (current)     — current size
         * src (source)      — size of area that should be taken from the current image
         * dst (destination) — new size of the taken area that will be put to the new image
         */

        if ( is_null( $desHeight ) && is_null( $desWidth ) ) // Resizing is not required
        {
            return null;
        }

        if ( is_null( $desHeight ) ) // Height is set due to the width
        {
            if ( $desWidth === $curWidth || !$allowIncrease && $desWidth > $curWidth )
                return null;

            return Array(
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => $desWidth,
                'dstHeight' => round( $desWidth * $curHeight / $curWidth )
            );
        }

        if ( is_null( $desWidth ) ) // Width is set due to the width
        {
            if ( $desHeight === $curHeight || !$allowIncrease && $desHeight > $curHeight )
                return null;

            return Array(
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => round( $desHeight * $curWidth / $curHeight ),
                'dstHeight' => $desHeight
            );
        }

        if ( $sizing === static::SIZING_EXEC ) // Just resize to given size ignoring aspect ratio
        {
            if (
                $desHeight === $curHeight &&
                $desWidth  === $curWidth
                ||
                !$allowIncrease &&
                $desHeight  >  $curHeight &&
                $desWidth   >  $curWidth
            )
                return null;

            return Array(
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => $allowIncrease ? $desWidth  : min( $desWidth,  $curWidth  ),
                'dstHeight' => $allowIncrease ? $desHeight : min( $desHeight, $curHeight )
            );
        }

        if ( $sizing === static::SIZING_CONTAIN || $sizing === static::SIZING_COVER ) // Aspect ratio is preserved and image is fitted to the given size
        {
            $curRatio = $curWidth / $curHeight;
            $desRatio = $desWidth / $desHeight;

            if ( $sizing === static::SIZING_CONTAIN xor $curRatio > $desRatio ) {
                // Level by top and bottom edges
                $scale = $desHeight / $curHeight;
            } else {
                // Level by side edges
                $scale = $desWidth / $curWidth;
            }

            if ( !$allowIncrease && $scale > 1 )
                $scale = 1.0;

            if ( $scale == 1.0 && $desWidth > $curWidth && $desHeight > $curHeight )
                return null;

            // Scale factor is calculated. Calculating derived parameters.

            $srcWidth  = min( round( $desWidth  / $scale ), $curWidth  );
            $srcHeight = min( round( $desHeight / $scale ), $curHeight );
            $srcX      = round( ( $curWidth  - $srcWidth  ) * $alignHor );
            $srcY      = round( ( $curHeight - $srcHeight ) * $alignVer );

            return Array(
                'srcX'      => $srcX,
                'srcY'      => $srcY,
                'srcWidth'  => $srcWidth,
                'srcHeight' => $srcHeight,
                'dstWidth'  => min( $desWidth,  round( $curWidth  * $scale ) ),
                'dstHeight' => min( $desHeight, round( $curHeight * $scale ) )
            );
        }

        return null;
    }

    /**
     * Calculates distance between two vectors using «Taxicab geometry».
     *
     * @param float[] $arr1 Vector 1
     * @param float[] $arr2 Vector 2
     * @param int $l Vectors size
     * @return float
     */
    protected static function sqDistance( $arr1, $arr2, $l )
    {
        $s = 0;

        for( $i = 0; $i < $l; ++$i )
            $s += abs( $arr1[ $i ] - $arr2[ $i ] );

        return $s;
    }

    /**
     * Encodes an image to the given format.
     *
     * @param resource $bitmap Image DG resource
     * @param string|null $file File to which image should be written. If null, byte code of the encoded image will be
     *  printed to the main output.
     * @param int $type Image format to encode. Value is one of global constants IMAGETYPE_*.
     * @param float $quality Save quality. 0 — small size, 1 — good quality.
     * @return bool Is the image encoded successfully
     * @throws \InvalidArgumentException Given format is not supported
     */
    protected static function saveImage( $bitmap, $file, $type, $quality = 0.75 )
    {
        switch ( $type ) {
            case IMAGETYPE_JPEG:
                return @imagejpeg( $bitmap, $file, $quality * 100 );

            case IMAGETYPE_BMP:
            case IMAGETYPE_WBMP:
                return @imagewbmp( $bitmap, $file );

            case IMAGETYPE_PNG:
                @imagealphablending( $bitmap, false );
                @imagesavealpha( $bitmap, true );
                $result = @imagepng( $bitmap, $file, 9 );
                @imagealphablending( $bitmap, true );
                return $result;

            case IMAGETYPE_GIF:
                return @imagegif( $bitmap, $file );

            case IMAGETYPE_WEBP:
                if ( function_exists( 'imagewebp' ) )
                    return @imagewebp( $bitmap, $file );
                break;
        }

        throw new \InvalidArgumentException( 'Given image type `' . $type . '` is unknown.' );
    }

    /**
     * Gets image format for the given extension.
     *
     * @param string $extension Extension name
     * @return int One of global constants IMAGETYPE_*
     */
    protected static function extension2imageType( $extension )
    {
        $dotPos = strrpos( $extension, '.' );
        if ( $dotPos !== false )
            $extension = substr( $extension, $dotPos + 1 );

        switch ( strtolower( $extension ) ) {
            case 'jpg': case 'jpeg': return IMAGETYPE_JPEG;
            case 'png':  return IMAGETYPE_PNG;
            case 'gif':  return IMAGETYPE_GIF;
            case 'bmp':  return IMAGETYPE_BMP;
            case 'wbmp': return IMAGETYPE_WBMP;
            case 'webp': return IMAGETYPE_WEBP;
            default:     return IMAGETYPE_UNKNOWN;
        }
    }

    /**
     * Gets extension for the given image format.
     *
     * @param int $type Format. Value is one of global constants IMAGETYPE_*
     * @param bool $includeDot Should dot be added to the begin of the extension
     * @return string Extension name
     */
    protected static function imageType2extension( $type, $includeDot = false )
    {
        switch ( $type ) {
            case IMAGETYPE_JPEG: return ( $includeDot ? '.' : '' ) . 'jpg';
            case IMAGETYPE_WEBP: return ( $includeDot ? '.' : '' ) . 'webp';
            default: return image_type_to_extension( $type, $includeDot );
        }
    }

    /**
     * Copies content of an image resource to the new resource.
     *
     * @param resource $bitmap DG resource to copy
     * @return resource Copied DG resource
     * @throws \InvalidArgumentException If given resource is not a DG resource
     * @throws \Exception
     */
    protected static function copyBitmap( $bitmap )
    {
        if ( !is_resource( $bitmap )) {
            throw new \InvalidArgumentException( 'Argument $bitmap expected to be resource, ' . gettype( $bitmap ) . 'given.' );
        }

        if ( get_resource_type( $bitmap ) !== 'gd' ) {
            throw new \InvalidArgumentException( 'The resource from the $bitmap argument is not image resource.' );
        }

        $width  = @imagesx( $bitmap );
        $height = @imagesy( $bitmap );

        if ( imageistruecolor( $bitmap ) ) {
            $bitmap2 = @imagecreatetruecolor( $width, $height );
        } else {
            $bitmap2 = @imagecreate( $width, $height );
            @imagepalettecopy( $bitmap2, $bitmap );
        }

        @imagealphablending( $bitmap2, false );
        $result = @imagecopy( $bitmap2, $bitmap, 0, 0, 0, 0, $width, $height );

        if ( !$bitmap2 || !$result ) {
            throw new \Exception( 'Can\'t copy image resource. Perhaps not enough RAM.' );
        }

        return $bitmap2;
    }
}
