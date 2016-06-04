# Image Tools

Simple Image editing tool for PHP. It uses immutable object style. It means that the image object can't be changed after 
creation, the result of any modifying method of an image object is the new image object.


## Installation

### Composer

Add this to the `composer.json` file:

```json
"require": {
  "aiger-team/image-tools": "1.0.*"
}
```

and then call:

```sh
composer install
```

Or use the composer require command:

```sh
composer require aiger-team/image-tools
```

### Manually

Download files [from GitHub](https://github.com/AigerTeam/ImageTools/archive/master.zip), put them to your code directory
and require them into your PHP code.


## Examples

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
$thumbsFiles = array_map( function( $size ) use ( $image, $watermark )
{
	$thumb = $image->resize( $size[0], $size[1], false, $image::SIZING_COVER );	// Original $image is not modified so thumbs may be created in any order
	
	if ( $size[2] )
		$thumb = $thumb->stamp( $watermark, 0.2 );	// Watermark size is relative, not pixel
		
	return $thumb->toUncertainFile( 'uploads/thumbs' );	// File name and format is set automatically
}, $thumbSizes );
```


## Reference

None of methods trigger errors or warnings. Instead of that they throw exceptions. If some of them triggers error please
[report us](https://github.com/AigerTeam/ImageTools/issues/new).

### Creating Image object

An `Image` object can be created by various ways.

#### 1. Using one of the ImageFactory object method

```php
use AigerTeam\ImageTools\ImageFactory;

$factory = new ImageFactory();
$image = $factory->blank( 100, 150 );
```

See PHPDoc in the `ImageFactory` [class code](https://github.com/AigerTeam/ImageTools/blob/master/src/ImageFactory.php) 
for more details and the list of all image creating methods.

#### 2. Passing DG image resource to the Image constructor

```php
use AigerTeam\ImageTools\Image;

$resource = imagecreatetruecolor( 100, 150 );
$image = new Image( $resource );
```

The resource passed to the constructor become an `Image` object own. So the resource can be modified inside the 
constructor and the resource will be automatically destroyed on the `Image` object destruction.

### Processing an image

An `Image` object is immutable. Image methods that modify image return the new object or the original object (if it's
not modified). So this code is **incorrect**:

```php
// WRONG! Don't do this or you will be fired.
$image->resize( 200, 150 );
$image->toFile( 'image.jpg' );
```

This one is **correct**:

```php
// Variant 1
$image = $image->resize( 200, 150 );
$image->toFile( 'image.jpg' );

// Variant 2
$image
	->resize( 200, 150 )
	->toFile( 'image.jpg' );
```

See PHPDoc in the `Image` [class code](https://github.com/AigerTeam/ImageTools/blob/master/src/Image.php) for more 
details and the list of all methods.


## Version compatibility

Versions are backward compatible within minor versions. For example versions `1.1.3` and `1.1.5` are backward compatible,
but versions `1.1.3` and `1.2.1` may be not compatible. So we advice you to set specific minor version in the composer 
configuration, for example `1.0.*`.


## License

ImageTools is licensed under the MIT License. See the LICENSE file for details.
