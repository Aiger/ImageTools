<?php

include '../../src/Image.php';
include '../../src/ImageFactory.php';

use AigerTeam\ImageTools\Image;
use AigerTeam\ImageTools\ImageFactory;

// Обработка изображения
$image = ( new ImageFactory() )
    ->openFile( 'example.jpg' )
    ->resize(
        $_GET[ 'width' ],
        $_GET[ 'height' ],
        $_GET[ 'allowIncrease' ],
        $_GET[ 'sizing' ],
        $_GET[ 'alignHor' ],
        $_GET[ 'alignVer' ]
    );

// Построение заголовков для вывода в ответ
$format = $image->getRecommendedFormat();
switch ( $format ) {
	case Image::FORMAT_JPG:
		$mime = 'image/jpeg';
		break;
	default:
		$mime = 'image/' . $format;
}

// Вывод
header( 'Content-type: ' . $mime );
$image->display();