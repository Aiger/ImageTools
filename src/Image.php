<?php

namespace AigerTeam\ImageTools;

/**
 * Class Image
 *
 * Класс-обёртка для изображений, предоставляющая объектный интерфейс и множество необходимых в повседневной
 * деятельности методов.
 *
 * Для создания объекта этого класса можно:
 *  - передать ресурс изображения в конструктор;
 *  - воспользоваться фабрикой ImageFactory.
 *
 * @author Finesse
 * @author maindefine
 * @package AigerTeam\ImageTools
 */
class Image
{
    /**
     * Изображение масштабируется так, чтобы сохранять пропорции и не обрезаться, при этом помещаться в заданную
     * область.
     */
    const SIZING_CONTAIN = 'contain';

    /**
     * Изображение масштабируется так, чтобы сохранять пропорции и занимать всю заданную область, при этом края могут
     * оказаться обрезанными.
     */
    const SIZING_COVER = 'cover';

    /**
     * Изображение масштабируется так, чтобы занимать всю заданную область и не обрезаться, при этом пропорции могут
     * быть нарушены.
     */
    const SIZING_EXEC = 'exec';

    /**
     * Формат JPG
     */
    const FORMAT_JPG = 'jpg';

    /**
     * Формат PNG
     */
    const FORMAT_PNG = 'png';

    /**
     * Формат GIF
     */
    const FORMAT_GIF = 'gif';

    /**
     * @var \resource Ресурс изображения.
     */
    protected $bitmap;

    /**
     * @var bool Имеет ли изображение прозрачность.
     */
    protected $isTransparent = false;


    /**
     * @param \resource $bitmap DG-ресурс изображения
     * @param bool $isTransparent Имеет ли переданное в $bitmap изображение прозрачность. Не существенный аргумент,
     * используется только для выбора формата при сохранении.
     * @throws \InvalidArgumentException Если указан не ресурс или указанный ресурс не является ресурсом изображения
     */
    public function __construct( $bitmap, $isTransparent = false )
    {
        if ( !is_resource( $bitmap ) )
            throw new \InvalidArgumentException( 'Аргумент $bitmap не является ресурсом.' );

        if ( get_resource_type( $bitmap ) !== 'gd' )
            throw new \InvalidArgumentException( 'Указанный ресурс (аргумент $bitmap) не является ресурсом изображения.' );

        $this->bitmap = $bitmap;
        $this->isTransparent = $isTransparent;
    }


    /**
     * Клонирует изображение, в том числе полностью копирует его ресурс.
     */
    public function __clone()
    {
        if( !isset( $this->bitmap ) )
            return;

        $width  = $this->getWidth();
        $height = $this->getHeight();

        $oldBitmap = $this->bitmap;
        $newBitmap = imagecreatetruecolor( $width, $height );

        imagecopy( $newBitmap, $oldBitmap, 0, 0, 0, 0, $width, $height );

        $this->bitmap = $newBitmap;
    }

    
    /**
     * Уничожает изображение, в том числе уничтожает его ресурс.
     */
    public function __destruct()
    {
        try {
            imagedestroy( $this->bitmap );
        } catch ( \Exception $e ) {};
    }


    /**
     * @return int Ширина изображения в пикселях
     */
    public function getWidth()
    {
        return imagesx( $this->bitmap );
    }


    /**
     * @return int Высота изображения в пикселях
     */
    public function getHeight()
    {
        return imagesy( $this->bitmap );
    }


    /**
     * Возвращает название рекомендуемого для этого изображения формата, которое можно подставить в имя файла.
     *
     * @return string Совпадает с одной из констант FORMAT_* этого класса.
     */
    public function getRecommendedFormat()
    {
        if ( $this->isTransparent )
            return static::FORMAT_PNG;

        return static::FORMAT_JPG;
    }


    /**
     * Изменяет размер изображения.
     *
     * @param int|null $width Желаемая ширина изображения. Если null, то будет рассчитана из высоты с сохранением
     * пропорций. Нужно обязательно указать ширину и/или высоту.
     * @param int|null $height Желаемая высота изображения. Если null, то будет рассчитана из шырины с сохранением
     * пропорций. Нужно обязательно указать ширину и/или высоту.
     * @param bool $allowIncrease Позволять ли увеличивать изображение, иначе будет только уменьшаться. В любом случае
     * все остальные условия будут продолжают действовать.
     * @param string $sizing Определяет, как изображение будет масштабироваться, чтобы занять указанную область.
     * Влияет только если указаны ширина и высота. Смотрите константы SIZING_*, чтобы узнать варианты.
     * @param float $alignHor Положение изображения по горизонтали при обрезке (от 0 (виден левый край) до 1 (виден
     * правый край)). Влияет только если указаны ширина и высота и значение и значение аргумента $sizing равно
     * Image::SIZING_COVER.
     * @param float $alignVer Положение изображения по вертикали при обрезке (от 0 (виден верхний край) до 1 (виден
     * нижний край)). Влияет только если указаны ширина и высота и значение и значение аргумента $sizing равно
     * Image::SIZING_COVER.
     * @return bool Прошло ли изменение размера успешно
     *
     * @see Image::SIZING_CONTAIN
     * @see Image::SIZING_COVER
     * @see Image::SIZING_EXEC
     */
    function resize(
        $width = null,
        $height = null,
        $allowIncrease = false,
        $sizing = self::SIZING_CONTAIN,
        $alignHor = 0.5,
        $alignVer = 0.5
    ) {
        if( !isset( $this->bitmap ) )
            return false;

        // Проверка данных

        if ( is_null( $width ) && is_null( $height ) )
            throw new \InvalidArgumentException( 'Не указана ни ширина, ни высота изображения.' );

        if ( !is_null( $width ) ) {
            if ( !is_numeric( $width ) )
                throw new \InvalidArgumentException( 'Ширина должна быть числом (количеством пикселей).' );

            $width = (int)max( $width, 1 );
        }

        if ( !is_null( $height ) ) {
            if ( !is_numeric( $height ) )
                throw new \InvalidArgumentException( 'Высота должна быть числом (количеством пикселей).' );

            $height = (int)max( $height, 1 );
        }


        // Вычисление параметров для масштабирования
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
            return true;


        // Непосредственно масштабирование
        $bitmap = imagecreatetruecolor( $params[ 'dstWidth' ], $params[ 'dstHeight' ] );
        imagealphablending( $bitmap, false );
        imagecopyresampled(
            $bitmap,
            $this->bitmap,
            0,                     0,
            $params[ 'srcX' ],     $params[ 'srcY' ],
            $params[ 'dstWidth' ], $params[ 'dstHeight' ],
            $params[ 'srcWidth' ], $params[ 'srcHeight' ] );
        imagealphablending( $bitmap, true );
        imagedestroy( $this->bitmap );
        $this->bitmap = $bitmap;

        return true;
    }


    /**
     * Пишет надпись на изображении.
     *
     * @param string $text Текст, который нужно написать
     * @param string $font Путь к файлу шрифта, которым нужно сделать надпись, на сервере
     * @param int $fontSize Размер шрифта
     * @param int $color Цвет (массив с индексами r, g, b и по желанию a)
     * @param int $x Координата X блока текста
     * @param int $y Координата Y блока текста
     * @param float $alignHor Положение текста по горизонтали (от 0 (слева от указанной точки) до 1 (справа от указанной
     * точки))
     * @param float $alignVer Положение текста по вертикали (от 0 (снизу от указанной точки) до 1 (сверху от указанной
     * точки))
     * @param float $angle Угол поворота текста в градусах (против часовой стрелки)
     * @return bool Удалось ли написать текст
     */
    function write(
        $text,
        $font,
        $fontSize = 12,
        Array $color = [ 'r' => 0, 'g' => 0, 'b' => 0 ],
        $x = 0,
        $y = 0,
        $alignHor = 0,
        $alignVer = 0,
        $angle = 0
    ) {
        $coord = imagettfbbox( $fontSize, $angle, $font, $text );
        $width  = $coord[ 2 ] - $coord[ 0 ];
        $height = $coord[ 1 ] - $coord[ 7 ];
        $x -= $width  * $alignHor;
        $y -= $height * $alignVer;
        $color = static::allocateColor( $this->bitmap, $color );

        return !!@imagettftext( $this->bitmap, $fontSize, $angle, $x, $y, $color, $font, $text );
    }


    /**
     * Вставляет изображение в текущее.
     *
     * @param self $image Вставляемое изображение
     * @param int $dstX Координата X на текущем изображении, куда вставить новое. По умолчанию, 0.
     * @param int $dstY Координата Y на текущем изображении, куда вставить новое. По умолчанию, 0.
     * @param int $srcX Координата X вставляемой области вставляемого изображения. По умолчанию, 0.
     * @param int $srcY Координата Y вставляемой области вставляемого изображения. По умолчанию, 0.
     * @param int|null $dstWidth Новая ширина вставляемой области. Если null, то не меняется.
     * @param int|null $dstHeight Новая Высота вставляемой области. Если null, то не меняется.
     * @param int|null $srcWidth Ширина вставляемой области вставляемого изображения. Если null, то вся ширина изображения.
     * @param int|null $srcHeight Высота вставляемой области вставляемого изображения. Если null, то вся высота изображения.
     * @return bool Успешно ли произведена вставка
     */
    function insertImage(
        self $image,
        $dstX = 0,
        $dstY = 0,
        $srcX = 0,
        $srcY = 0,
        $dstWidth  = null,
        $dstHeight = null,
        $srcWidth  = null,
        $srcHeight = null
    ) {
        if( !isset( $this->bitmap ) || !isset( $image->bitmap ) )
            return false;

        if( !is_numeric( $dstX ) ) $dstX = 0;
        if( !is_numeric( $dstY ) ) $dstY = 0;
        if( !is_numeric( $srcX ) ) $srcX = 0;
        if( !is_numeric( $srcY ) ) $srcY = 0;
        if( !is_numeric( $srcWidth  ) ) $srcWidth  = $image->getWidth() - $srcX;
        if( !is_numeric( $srcHeight ) ) $srcHeight = $image->getHeight() - $srcY;
        if( !is_numeric( $dstWidth  ) ) $dstWidth  = $srcWidth;
        if( !is_numeric( $dstHeight ) ) $dstHeight = $srcHeight;

        return imagecopyresampled(
            $this->bitmap,
            $image->bitmap,
            $dstX,     $dstY,
            $srcX,     $srcY,
            $dstWidth, $dstHeight,
            $srcWidth, $srcHeight
        );
    }


    /**
     * Вращает изображение
     *
     * @param float $angle Угол, выраженный в градусах, против часовой стрелки
     * @return bool Успешно ли выполнено вращение
     */
    function rotate( $angle )
    {
        $bitmap = imagerotate( $this->bitmap, $angle, $this->isTransparent ? imagecolorallocatealpha( $this->bitmap, 0, 0, 0, 127 ) : 0 );

        if ( !$bitmap )
            return false;

        imagedestroy( $this->bitmap );
        $this->bitmap = $bitmap;

        return true;
    }


    /**
     * Находит доминирующие цвета на изображении алгоритмом K-средних.
     *
     * @param int $amount Количество цветов, которые нужно найти
     * @param true $sort Производить ли сортировку выходного массива по количеству цвета на изображении
     * @param int $maxPreSize Размер, до которого уменьшается изображение, перед тем, как применить алгоритм (чем меньше,
     * тем быстрее и менее точно)
     * @param int $epsilon Погрешность, при достижении которой нужно остановить поиск (чем меньше, тем медленнее и
     * точнее поиск)
     * @return int[][]|null Массив цветов (массив с индексами r, g и b). Null в случае непредвиденной ошибки.
     */
    public function getKMeanColors( $amount = 1, $sort = true, $maxPreSize = 30, $epsilon = 2 )
    {
        if( !isset( $this->bitmap ) )
            return null;

        $eps    = $epsilon;
        $width  = $this->getWidth();
        $height = $this->getHeight();
        $newW   = min( $width,  $maxPreSize );
        $newH   = min( $height, $maxPreSize );

        $bitmap = imagecreatetruecolor( $newW, $newH );
        imagecopyresized( $bitmap, $this->bitmap, 0, 0, 0, 0, $newW, $newH, $width, $height );

        $pixelsAmount = $newW * $newH;
        $pixels = Array();
        for ( $i = 0; $i < $newW; ++$i )
            for ( $j = 0; $j < $newH; ++$j ) {
                $rgb = imagecolorat( $bitmap, $i, $j );
                $pixels[] = Array(
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8)  & 0xFF,
                     $rgb        & 0xFF
                );
            }

        imagedestroy( $bitmap );
        
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
                    $dist = self::sqDistance( $old, $clusters[ $i ], 3 );
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

        $ret = [];
        for ( $i = 0; $i < $amount; ++$i )
            $ret[] = Array(
                'r' => $clusters[ $i ][ 0 ],
                'g' => $clusters[ $i ][ 1 ],
                'b' => $clusters[ $i ][ 2 ]
            );

        return $ret;
    }


    /**
     * Выводит сохранённое изображение в основной вывод. Может использоваться для отправки изображение в качестве ответа
     * на клиент.
     *
     * @param string|null $format Формат, в который нужно сохранить изображение. Если null, то будет подобран
     * автоматически. Чтобы узнать список вариантов, смотрите константы FORMAT_* этого класса.
     * @param float|null $quality Качество сохранения (от 0 до 1)
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     * @throws \Exception В случае непредвиденной ошибки
     */
    public function display( $format = null, $quality = null )
    {
        if ( !isset( $this->bitmap ) )
            throw new \Exception( 'В этом объекте нет данных изображения.' );

        if ( empty( $format ) )
            $format = $this->getRecommendedFormat();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        switch ( $format ) {
            case self::FORMAT_JPG:
            case 'jpeg':
            case 'jpg':
                imagejpeg( $this->bitmap, null, $quality * 100 );
                break;
            case self::FORMAT_PNG:
                imagesavealpha( $this->bitmap, true );
                imagepng( $this->bitmap );
                break;
            default: 
                $func = 'image' . $format;

                if ( !function_exists( $func ) )
                    throw new \InvalidArgumentException( 'Указан неизвестный формат (' . $format . ').' );

                @$func( $this->bitmap );
                break;
        }
    }


    /**
     * Сохраняет изображение в файл.
     *
     * @param string $path Путь к файлу (без формата и его точки) на сервере, в который нужно сохранить изображение.
     * Например, /var/tmp/picture
     * @param string|null $format Формат, в который нужно сохранить изображение. Если null, то будет подобран
     * автоматически. Чтобы узнать список вариантов, смотрите константы FORMAT_* этого класса.
     * @param float|null $quality Качество сохранения (от 0 до 1)
     * @return string Полный путь к файлу включая формат, в котором он был сохранён. Например: /var/tmp/picture.jpg
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     * @throws \Exception В случае непредвиденной ошибки
     */
    public function toFile( $path, $format = null, $quality = null )
    {
        if ( !isset( $this->bitmap ) )
            throw new \Exception( 'В этом объекте нет данных изображения.' );

        if ( empty( $format ) )
            $format = $this->getRecommendedFormat();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        $src = $path . '.' . $format;

        switch ( $format ) {
            case self::FORMAT_JPG:
            case 'jpeg':
            case 'jpg':
                imagejpeg( $this->bitmap, $src, $quality * 100 );
                break;
            case self::FORMAT_PNG:
                imagesavealpha( $this->bitmap, true );
                imagepng( $this->bitmap, $src );
                break;
            default:
                $func = 'image' . $format;

                if ( !function_exists( $func ) )
                    throw new \InvalidArgumentException( 'Указан неизвестный формат (' . $format . ').' );

                @$func( $this->bitmap, $src );
                break;
        }

        return $src;
    }


    /**
     * Генерирует цвет для использования в функциях, работающих с ресурсами изображения.
     *
     * @param \resource $bitmap Ресурс изображения, для которого нужно сгенерировать цвет
     * @param int[]|null $color Цвет заливки изображения (массив с индексами r, g, b и по желанию a). Если указать null,
     * то изображение будет полностью прозрачным.
     * @return int
     * @throws \InvalidArgumentException Если указан не ресурс или указанный ресурс не является ресурсом изображения
     */
    public static function allocateColor( $bitmap, Array $color = null )
    {
        if ( !is_resource( $bitmap ) )
            throw new \InvalidArgumentException( 'Аргумент $bitmap не является ресурсом.' );

        if ( get_resource_type( $bitmap ) !== 'gd' )
            throw new \InvalidArgumentException( 'Указанный ресурс (аргумент $bitmap) не является ресурсом изображения.' );

        return is_null( $color )
            ? imagecolorallocatealpha( $bitmap, 0, 0, 0, 127 )
            : imagecolorallocatealpha(
                $bitmap,
                empty( $fill[ 'r' ] ) ? 0 : $fill[ 'r' ],
                empty( $fill[ 'g' ] ) ? 0 : $fill[ 'g' ],
                empty( $fill[ 'b' ] ) ? 0 : $fill[ 'b' ],
                empty( $fill[ 'a' ] ) ? 0 : $fill[ 'a' ]
            );
    }


    /**
     * Рассчитывает параметры масштабирования изображения.
     *
     * Смотри описание к методу resize, чтобы узнать смысл аргументов.
     *
     * @see Image::resize
     *
     * @param int $curWidth Текущая ширина изображения
     * @param int $curHeight Текущая высота изображения
     * @return int[]|null Массив параметров. Если null, значит, масштабирование не требуется. Параметры массива:
     *  - srcX - координата x начала области в исходном изображении;
     *  - srcY - координата y начала области в исходном изображении;
     *  - srcWidth - ширина области в исходном изображении;
     *  - srcHeight - высота области в исходном изображении;
     *  - dstWidth - новая ширина области;
     *  - dstHeight - новая высота области.
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
         * des (desired)     — желаемый размер
         * cur (current)     — текущий размер
         * src (source)      — размер участка, который нужно взять из текущего изображения
         * dst (destination) — размер, до которого нужно масштабировать взятый участок, перед тем как положить его в новый холст
         */

        if ( is_null( $desHeight ) && is_null( $desWidth ) ) // Размер менять не требуется
        {
            return null;
        }
        if ( is_null( $desHeight ) ) // Приведение ширины к нужному значению
        {
            if ( $desWidth === $curWidth || !$allowIncrease && $desWidth > $curWidth )
                return null;

            return [
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => $desWidth,
                'dstHeight' => round( $desWidth * $curHeight / $curWidth )
            ];
        }
        elseif ( is_null( $desWidth ) ) // Приведение высоты к нужному значению
        {
            if ( $desHeight === $curHeight || !$allowIncrease && $desHeight > $curHeight )
                return null;

            return [
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => round( $desHeight * $curWidth / $curHeight ),
                'dstHeight' => $desHeight
            ];
        }
        elseif ( $sizing === static::SIZING_EXEC ) // Просто изменить размеры до указанных, плюнув на пропорции
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

            return [
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => $allowIncrease ? $desWidth  : min( $desWidth,  $curWidth  ),
                'dstHeight' => $allowIncrease ? $desHeight : min( $desHeight, $curHeight )
            ];
        }
        elseif ( $sizing === static::SIZING_CONTAIN || $sizing === static::SIZING_COVER ) // Пропорции изображения сохраняются и оно вписывается в размер
        {
            $curRatio = $curWidth / $curHeight;
            $desRatio = $desWidth / $desHeight;

            if ( $sizing === static::SIZING_CONTAIN xor $curRatio > $desRatio ) {
                // Равнение по вертикальным краям
                $scale = $desHeight / $curHeight;
            } else {
                // Равнение по горизонтальным краям
                $scale = $desWidth / $curWidth;
            }

            if ( !$allowIncrease && $scale > 1 )
                $scale = 1.0;

            if ( $scale == 1.0 && $desWidth > $curWidth && $desHeight > $curHeight )
                return null;

            // Коэффициент масштабирования найден, осталось рассчитать коэффициенты сдвига

            $srcWidth  = min( round( $desWidth  / $scale ), $curWidth  );
            $srcHeight = min( round( $desHeight / $scale ), $curHeight );
            $srcX      = round( ( $curWidth  - $srcWidth  ) * $alignHor );
            $srcY      = round( ( $curHeight - $srcHeight ) * $alignVer );

            return [
                'srcX'      => $srcX,
                'srcY'      => $srcY,
                'srcWidth'  => $srcWidth,
                'srcHeight' => $srcHeight,
                'dstWidth'  => min( $desWidth,  round( $curWidth  * $scale ) ),
                'dstHeight' => min( $desHeight, round( $curHeight * $scale ) )
            ];
        }

        return null;
    }


    /**
     * Находит расстояние между двумя векторами в пространственной метрике «Расстояние городских кварталов»
     *
     * @param float[] $arr1 Вектор 1
     * @param float[] $arr2 Вектор 2
     * @param int $l Размер векторов
     * @return float
     */
    protected static function sqDistance( $arr1, $arr2, $l )
    {
        $s = 0;

        for($i = 0; $i < $l; ++$i)
            $s += $arr1[$i] > $arr2[$i] ? ( $arr1[$i] - $arr2[$i] ) : ( $arr2[$i] - $arr1[$i] );

        return $s;
    }
}