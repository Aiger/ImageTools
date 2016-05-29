# Image Tools

Simple Image editing tool for PHP. It uses immutable object style. It means that none of methods changes object. 
Instead of that image-changing methods return the new image object (if image is changed) or the original object (if 
image isn't changed).


## Installation

### Composer

Add this to the `composer.json` file:

```json
"require": {
  "aiger-team/image-tools": "1.0.*"
}
```

or use composer require command:

```sh
composer require aiger-team/image-tools
```

### Manually

Download files [from GitHub](https://github.com/AigerTeam/ImageTools/archive/master.zip), put them to your code directory
and require them into your PHP code.


## Quick start

Resize image:

```php
use AigerTeam\ImageTools\ImageFactory;

( new ImageFactory() )
	->openFile( 'image.jpg' )
	->resize( 600, 500 )
	->toFile( 'image-resized.jpg' );
```

Create a few thumbnails from an uploaded image and put a watermark on some of them:

```php
use AigerTeam\ImageTools\ImageFactory;

// List of thumb to create. The values are width, height and "put watermark?".
$thumbsSizes = [
	[ 150, 80, false ],
	[ 300, 200, true ],
	[ 500, 400, true ]
];

// Open uploaded image and watermark image
$factory   = new ImageFactory();
$image     = $factory->openFile( $_FILES[ 'image' ][ 'tmp_name' ] );
$watermark = $factory->openFile( 'watermark.png' );

// Make thumbs
$thumbsFiles = array_map( function( $size ) use ( $image, $watermark ) {
	$thumb = $image->resize( $size[0], $size[1], false, $image::SIZING_COVER );
	
	if ( $size[2] )
		$thumb = $thumb->stamp( $watermark, 0.2 );	// Don't need to calculate pixel-sizes
		
	return $thumb->toUncertainFile( 'uploads/thumbs' );
}, $thumbSizes );
```


## Reference

Coming soon... See PHPDoc in the code yet.


## Version compatibility

Versions are backward compatible within minor versions. For example versions `1.1.3` and `1.1.5` are backward compatible,
but versions `1.1.3` and `1.2.1` aren't. So we advice you to set specific minor version in the composer configuration, 
for 
example `1.0.*`.


## License

ImageTools is licensed under the MIT License. See the LICENSE file for details.
