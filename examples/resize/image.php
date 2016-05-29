<?php

include '../../src/Image.php';
include '../../src/ImageFactory.php';
include '../../src/exceptions/FileException.php';
include '../../src/exceptions/ImageFileException.php';

use AigerTeam\ImageTools\ImageFactory;

// Processing image
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

// Output image
header( 'Content-type: ' . image_type_to_mime_type( $image->getRecommendedType() ) );
$image->display();