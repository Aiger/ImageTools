<?php

namespace AigerTeam\ImageTools;

use AigerTeam\ImageTools\Exceptions\FileException;

if ( !defined( 'IMAGETYPE_WEBP' ) ) define( 'IMAGETYPE_WEBP', 117 );

/**
 * Class Image
 *
 * Класс-обёртка для изображений, предоставляющая объектный интерфейс, позволяющий делать цепочки вызовов, и множество
 * необходимых в повседневной деятельности методов.
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
     * @var resource Ресурс изображения. Предполагается, что:
     *   - после выполнения конструктора в этом атрибуте всегда есть значение указанного типа;
     *   - значение параметра imagealphablending всегда установлено в true.
     */
    protected $bitmap;

    /**
     * @var bool Имеет ли изображение прозрачность.
     */
    protected $isTransparent = false;


    /**
     * @param resource $bitmap DG-ресурс изображения
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

        @imagealphablending( $this->bitmap, true );
    }


    /**
     * Клонирует изображение, в том числе полностью копирует его ресурс.
     */
    public function __clone()
    {
        try {
            $this->bitmap = $this->toResource();
        } catch ( \Exception $e ) {
            $this->bitmap = null;
        }
    }


    /**
     * Уничтожает изображение, в том числе уничтожает его ресурс.
     */
    public function __destruct()
    {
        @imagedestroy( $this->bitmap );
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
     * Возвращает рекомендуемый для этого изображения формат.
     *
     * @return int Значение одной из глобальных констант IMAGETYPE_*
     */
    public function getRecommendedType()
    {
        if ( $this->isTransparent )
            return IMAGETYPE_PNG;

        return IMAGETYPE_JPEG;
    }


    /**
     * Возвращает рекомендуемое расширение для сохранения этого изображения.
     *
     * @param bool $includeDot Включать ли точку в название расшинеия
     * @return string Название расширения, которое можно подставить в имя файла
     */
    public function getRecommendedExtension( $includeDot = false )
    {
        return static::imageType2extension( $this->getRecommendedType(), $includeDot );
    }


    /**
     * Изменяет размер изображения. Возвращается копия, текущий объект не модифицируется.
     *
     * @param int|null $width Желаемая ширина изображения. Если null, то будет рассчитана из высоты с сохранением
     * пропорций. Нужно обязательно указать ширину и/или высоту.
     * @param int|null $height Желаемая высота изображения. Если null, то будет рассчитана из ширины с сохранением
     * пропорций. Нужно обязательно указать ширину и/или высоту.
     * @param bool $allowIncrease Позволять ли увеличивать изображение. Если нет, то изображение будет только
     * уменьшаться, а итоговый размер может не совпасть с указанным. В любом случае все остальные условия будут
     * соблюдены.
     * @param string $sizing Определяет, как изображение будет масштабироваться, чтобы занять указанную область.
     * Влияет только если указаны ширина и высота. Смотрите константы SIZING_*, чтобы узнать варианты.
     * @param float $alignHor Положение изображения по горизонтали при обрезке (от 0 (виден левый край) до 1 (виден
     * правый край)). Влияет только если указаны ширина и высота и значение и значение аргумента $sizing равно
     * Image::SIZING_COVER.
     * @param float $alignVer Положение изображения по вертикали при обрезке (от 0 (виден верхний край) до 1 (виден
     * нижний край)). Влияет только если указаны ширина и высота и значение и значение аргумента $sizing равно
     * Image::SIZING_COVER.
     * @return static Изображение с изменённым размером
     * @throws \Exception Если не удалось изменить размер
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
            return clone $this;


        // Непосредственно масштабирование
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
            throw new \Exception( 'Не удалось изменить размер изображения по неизвестной причине.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }


    /**
     * Пишет текст на изображении. Возвращается копия, текущий объект не модифицируется.
     *
     * @param string $text Текст, который нужно написать
     * @param string $font Путь к файлу шрифта, которым нужно сделать надпись, на сервере
     * @param float $fontSize Размер шрифта
     * @param float[] $color Цвет (массив с индексами r, g, b и по желанию a)
     * @param int $x Координата X блока текста
     * @param int $y Координата Y блока текста
     * @param float $alignHor Положение текста по горизонтали (от 0 (слева от указанной точки) до 1 (справа от указанной
     * точки))
     * @param float $alignVer Положение текста по вертикали (от 0 (снизу от указанной точки) до 1 (сверху от указанной
     * точки))
     * @param float $angle Угол поворота текста в градусах (против часовой стрелки)
     * @return static Изображение с надписью
     * @throws \Exception Если не удалось поместить текст
     */
    function write(
        $text,
        $font,
        $fontSize = 12.0,
        Array $color = Array( 'r' => 0, 'g' => 0, 'b' => 0 ),
        $x = 0,
        $y = 0,
        $alignHor = .0,
        $alignVer = .0,
        $angle = .0
    ) {
        try {
            $coord = @imagettfbbox( $fontSize, $angle, $font, $text );
            if ( $coord === false )
                throw new \Exception;

            $width  = $coord[ 2 ] - $coord[ 0 ];
            $height = $coord[ 1 ] - $coord[ 7 ];
            $x -= $width  * $alignHor;
            $y -= $height * $alignVer;
            $color = static::allocateColor( $this->bitmap, $color );
            $bitmap = $this->toResource();

            $result = @imagettftext( $bitmap, $fontSize, $angle, $x, $y, $color, $font, $text );
            if ( !$result )
                throw new \Exception;

        } catch ( \Exception $e ) {
            throw new \Exception( 'Не поместить текст на изображении по неизвестной причине.' );
        }

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }


    /**
     * Вставляет указанное изображение в текущее. Возвращается копия, текущий объект не модифицируется.
     *
     * @param self $image Вставляемое изображение
     * @param int $dstX Координата X на текущем изображении, куда вставить новое. По умолчанию, 0.
     * @param int $dstY Координата Y на текущем изображении, куда вставить новое. По умолчанию, 0.
     * @param int $srcX Координата X вставляемой области вставляемого изображения. По умолчанию, 0.
     * @param int $srcY Координата Y вставляемой области вставляемого изображения. По умолчанию, 0.
     * @param int|null $dstWidth Новая ширина вставляемой области. Если null, то такая же как $srcWidth.
     * @param int|null $dstHeight Новая Высота вставляемой области. Если null, то такая же как $srcHeight.
     * @param int|null $srcWidth Ширина вставляемой области вставляемого изображения. Если null, то вся ширина изображения.
     * @param int|null $srcHeight Высота вставляемой области вставляемого изображения. Если null, то вся высота изображения.
     * @return static Текущее изображение с вставленным
     * @throws \Exception Если не удалось вставить изображение
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
        if( !isset( $image->bitmap ) )
            throw new \Exception( 'В объекте вставляемого изображения нет данных изображения.' );

        if( !is_numeric( $dstX ) ) $dstX = 0;
        if( !is_numeric( $dstY ) ) $dstY = 0;
        if( !is_numeric( $srcX ) ) $srcX = 0;
        if( !is_numeric( $srcY ) ) $srcY = 0;
        if( !is_numeric( $srcWidth  ) ) $srcWidth  = $image->getWidth() - $srcX;
        if( !is_numeric( $srcHeight ) ) $srcHeight = $image->getHeight() - $srcY;
        if( !is_numeric( $dstWidth  ) ) $dstWidth  = $srcWidth;
        if( !is_numeric( $dstHeight ) ) $dstHeight = $srcHeight;

        $bitmap = $this->toResource();

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
            throw new \Exception( 'Не удалось вставить изображение по неизвестной причине.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }


    /**
     * Вращает изображение. Возвращается копия, текущий объект не модифицируется.
     *
     * @param float $angle Угол, выраженный в градусах, против часовой стрелки
     * @param float[]|null $underlay Цвет фона (массив с индексами r, g, b и по желанию a). Фон появляется, если
     * изображение повёрнуто на угол, не кратный 90°.
     * @return static Повёрнутое изображение
     * @throws \Exception Если не удалось повернуть изображение
     */
    function rotate( $angle, Array $underlay = null )
    {
        $bitmap = @imagerotate( $this->bitmap, $angle, static::allocateColor( $this->bitmap, $underlay ) );

        if ( !$bitmap )
            throw new \Exception( 'Не удалось повернуть изображение по неизвестной причине.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = $this->isTransparent;
        return $newImage;
    }


    /**
     * Делает изображение полупрозрачным. Возвращается копия, текущий объект не модифицируется.
     *
     * @param float $opacity Уровень непрозрачности (от 0 – полностью прозрачное, до 1 – без прозрачности)
     * @return static Изображение, к которому применена указанная прозрачность
     * @throws \InvalidArgumentException Если указанная прозрачность не является числом
     * @throws \Exception В случае непредвиденной ошибки
     */
    function setOpaque( $opacity )
    {
        if ( !is_numeric( $opacity ) )
            throw new \InvalidArgumentException( 'Opacity must be number, ' . gettype( $opacity ) . ' given.' );

        if ( $opacity >= 1 )
            return clone $this;

        $opacity = min( 1, max( 0, $opacity ) );

        $width  = $this->getWidth();
        $height = $this->getHeight();
        $bitmap = @imagecreatetruecolor( $width, $height );

        $color = @imagecolortransparent( $bitmap );
        @imagefill( $bitmap, 0, 0, $color );
        $result = @imagecopymerge( $bitmap, $this->bitmap, 0, 0, 0, 0, $width, $height, $opacity * 100 );

        if ( !$result )
            throw new \Exception( 'Не удалось сделать полупрозрачное изображение по неизвестной причине.' );

        $newImage = static::construct( $bitmap );
        $newImage->isTransparent = true;
        return $newImage;
    }


    /**
     * Находит доминирующие цвета на изображении алгоритмом K-средних.
     *
     * @param int $amount Количество цветов, которые нужно найти
     * @param bool $sort Производить ли сортировку выходного массива по количеству цвета на изображении
     * @param int $maxPreSize Размер, до которого уменьшается изображение, перед тем, как применить алгоритм (чем меньше,
     * тем быстрее и менее точно)
     * @param int $epsilon Погрешность, при достижении которой нужно остановить поиск (чем меньше, тем медленнее и
     * точнее поиск)
     * @return int[][]|null Массив цветов (массив с индексами r, g и b). Null в случае непредвиденной ошибки.
     * @throws \Exception В случае непредвиденной ошибки
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

        $bitmap = @imagecreatetruecolor( $newW, $newH );
        if ( !$bitmap ) throw new \Exception( 'Не удалось создать уменьшенное изображение.' );
        @imagealphablending( $bitmap, false );
        @imagecopyresized( $bitmap, $this->bitmap, 0, 0, 0, 0, $newW, $newH, $width, $height );

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

        @imagedestroy( $bitmap );

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
     * Сохраняет изображение в заданный формат и печатает его в главный вывод. Может использоваться для отправки
     * изображение в качестве ответа на клиент.
     *
     * @param int|null $type Формат, в который нужно сохранить изображение. Значение — значение одной из глобальных
     * констант IMAGETYPE_*. Если null, то будет подобран автоматически на основании рекомендуемого формата.
     * @param float|null $quality Качество сохранения (от 0 до 1)
     * @return static Сам себя
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     * @throws \Exception Если не удалось закодировать изображение в формат
     */
    public function display( $type = null, $quality = null )
    {
        if ( empty( $type ) )
            $type = $this->getRecommendedType();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        if ( !static::saveImage( $this->bitmap, null, $type, $quality ) )
            throw new \Exception( 'Не удалось сохранить файл.' );

        return $this;
    }


    /**
     * Сохраняет изображение в файл.
     *
     * @param string $file Путь к файлу на сервере, в который нужно сохранить изображение
     * @param int|null $type Формат, в который нужно сохранить изображение. Значение — значение одной из глобальных
     * констант IMAGETYPE_*. Если null, то будет подобран автоматически на основании названия файла и рекомендуемого
     * формата.
     * @param float|null $quality Качество сохранения (от 0 до 1)
     * @return static Сам себя
     * @throws FileException Если указанный путь недоступен для записи
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     * @throws \Exception Если не удалось закодировать изображение в формат
     */
    public function toFile( $file, $type = null, $quality = null )
    {
        // Очистка кеша информации о файле
        try {
            clearstatcache( true, $file );
        } catch (\Exception $e) {
            throw new FileException( 'Не удалось очистить кэш информации о файле. Ошибка: ' . $e->getMessage(), $file );
        }

        // Доступен ли файл для записи
        if ( file_exists( $file ) ) {
            if ( is_file( $file ) ) {
                if ( !is_writable( $file ) )
                    throw new FileException( 'Указанный файл недоступен для записи.', $file );
            } else {
                throw new FileException( 'Невозможно сохранить, потому что указанный путь не является файлом.', $file );
            }
        } elseif ( !is_writable( dirname( $file ) ) ) {
            throw new FileException( 'Указанный файл недоступен для записи.', $file );
        }

        // Подготовка параметров
        if ( !is_numeric( $quality ) )
            $quality = 1;

        // Подбор формата и сохранение

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

            if ( !$saved ) {
                $type = $this->getRecommendedType();
            }
        }

        if ( !$saved )
            $result = static::saveImage( $this->bitmap, $file, $type, $quality );

        if ( !$result )
            throw new \Exception( 'Не удалось сохранить файл.' );

        @clearstatcache( true, $file );

        return $this;
    }


    /**
     * Сохраняет файл в указанную директорию, при этом название подбирается автоматически в соответствии с условиями.
     *
     * @param string $dir Директория, в которую нужно сохранить файл (если не существует, будет создана)
     * @param string $name Желаемое название файла (без расширения)
     * @param bool $rewrite Позволять ли перезаписывать существующие файлы. Если указать false, то название будет
     * подобрано так, чтобы не совпадать с существующим файлом.
     * @param int|null $type Формат, в который нужно сохранить изображение. Значение — значение одной из глобальных
     * констант IMAGETYPE_*. Если null, то будет подобран автоматически на основании рекомендуемого формата.
     * @param float|null $quality Качество сохранения (от 0 до 1)
     * @return string Путь, по которому сохранён файл
     * @throws FileException При ошибках, связанных с файловыми операциями
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     * @throws \Exception Если не удалось закодировать изображение в формат
     */
    public function toUncertainFile( $dir, $name, $rewrite = false, $type = null, $quality = 1.0 )
    {
        if ( empty( $type ) )
            $type = $this->getRecommendedType();

        if ( !is_numeric( $quality ) )
            $quality = 1;

        static::prepareDir( $dir );

        $extension = static::imageType2extension( $type, true );
        $file = $dir . DIRECTORY_SEPARATOR . $name . $extension;
        $counter = 0;

        while ( file_exists( $file ) && ( $rewrite && !is_file( $file ) || !$rewrite ) )
            $file = $dir . DIRECTORY_SEPARATOR . $name . '('. ++$counter .')' . $extension;

        $this->toFile( $file, $type, $quality );

        return $file;
    }


    /**
     * Сохраняет это изображение в ресурс изображения.
     *
     * @return resource Новый ресурс. Операции над ним не затронут это изображение.
     * @throws \Exception В случае непредвиденной ошибки
     */
    public function toResource()
    {
        if ( !isset( $this->bitmap ) )
            throw new \Exception( 'В этом объекте нет данных изображения.' );

        $width  = $this->getWidth();
        $height = $this->getHeight();

        try {
            $bitmap = @imagecreatetruecolor( $width, $height );
            if ( !$bitmap )
                throw new \Exception;

            $result = @imagecopy( $bitmap, $this->bitmap, 0, 0, 0, 0, $width, $height );
            if ( !$result )
                throw new \Exception;

        } catch ( \Exception $e ) {
            throw new \Exception( 'Не удалось скопировать ресурс изображение. Возможно, не хватает оперативной памяти.' );
        }

        return $bitmap;
    }


    /**
     * Генерирует цвет для использования в функциях, работающих с ресурсами изображения.
     *
     * @param resource $bitmap Ресурс изображения, для которого нужно сгенерировать цвет
     * @param float[]|null $color Цвет заливки изображения (массив с индексами r, g, b и по желанию a). Если указать null,
     * то изображение будет полностью прозрачным. Значение альфа-канала: 0 –
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
                empty( $fill[ 'r' ] ) ? 0 : round( $fill[ 'r' ] ),
                empty( $fill[ 'g' ] ) ? 0 : round( $fill[ 'g' ] ),
                empty( $fill[ 'b' ] ) ? 0 : round( $fill[ 'b' ] ),
                empty( $fill[ 'a' ] ) ? 0 : round( ( 1 - $fill[ 'a' ] ) * 127 )
            );
    }


    /**
     * Создаёт объект своего класса. Нужен для возможности безопасного изменения аргументов конструктора в дочерних
     * классах.
     *
     * @param resource $bitmap DG-ресурс изображения
     * @return static
     * @throws \InvalidArgumentException Если указан не ресурс или указанный ресурс не является ресурсом изображения
     */
    protected static function construct( $bitmap )
    {
        return new static( $bitmap );
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

            return Array(
                'srcX'      => 0,
                'srcY'      => 0,
                'srcWidth'  => $curWidth,
                'srcHeight' => $curHeight,
                'dstWidth'  => $desWidth,
                'dstHeight' => round( $desWidth * $curHeight / $curWidth )
            );
        }

        if ( is_null( $desWidth ) ) // Приведение высоты к нужному значению
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

        if ( $sizing === static::SIZING_EXEC ) // Просто изменить размеры до указанных, плюнув на пропорции
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

        if ( $sizing === static::SIZING_CONTAIN || $sizing === static::SIZING_COVER ) // Пропорции изображения сохраняются и оно вписывается в размер
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


    /**
     * Кодирует изображение в заданный формат.
     *
     * @param resource $bitmap Ресурс изображения
     * @param string|null $file Файл, в который нужно записать закодированное изобраежние. Если null, то будет выведено
     * в главный вывод (на сайт).
     * @param int $type Формат, в который нужно сохранить изображение. Значение — значение одной из глобальных
     * констант IMAGETYPE_*.
     * @param float $quality Качество сохранения (от 0 до 1)
     * @return bool Удалось ли закодировать изображение
     * @throws \InvalidArgumentException Если указанный формат не поддерживается
     */
    protected static function saveImage( $bitmap, $file, $type, $quality = 1.0 )
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
                $result = @imagepng( $bitmap, $file );
                @imagealphablending( $bitmap, true );
                return $result;

            case IMAGETYPE_GIF:
                return @imagegif( $bitmap, $file );

            case IMAGETYPE_WEBP:
                if ( function_exists( 'imagewebp' ) )
                    return @imagewebp( $bitmap, $file );
                break;
        }

        throw new \InvalidArgumentException( 'Указан неизвестный формат (' . $type . ').' );
    }


    /**
     * Возвращает формат изображения на основе расширения.
     *
     * @param string $extension Название расширения (без точки в начале)
     * @return int Значение одной из глобальных констант IMAGETYPE_*
     */
    protected static function extension2imageType( $extension )
    {
        switch ( $extension ) {
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
     * Возвращает расширение изображения на основе формата.
     *
     * @param int $type Формат. Значение — значение одной из глобальных констант IMAGETYPE_*.
     * @param bool $includeDot Добавлять ли точку к названию формата
     * @return string Название расширения
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
     * Создаёт указанную директорию, если её нет. Создаёт без вопросов (если права есть).
     *
     * @param string $dir Адрес директории
     * @param int $mode Права на директорию, если нужно будет создать новую (см. chmod)
     * @throws FileException
     */
    protected static function prepareDir( $dir, $mode = 0775 )
    {
        try {
            clearstatcache( true, $dir );
        } catch (\Exception $e) {
            throw new FileException( 'Не удалось очистить кэш информации о директории. Ошибка: ' . $e->getMessage(), $dir );
        }

        if ( !is_dir( $dir ) ) {
            try {
                mkdir( $dir, 0777, true );
                @chmod( $dir, $mode );        // Если передать эти права в mkdir, то они будут выставлены некорректно
            } catch( \Exception $e ) {
                throw new FileException( 'Не удалось создать директории. Ошибка: ' . $e->getMessage(), $dir );
            }

            @clearstatcache( true, $dir );
        }
    }
}