# PHP-BLP
Blizzard BLP Image File Parser for PHP.

Requires [GD](http://php.net/manual/en/book.image.php) & [ImageMagick](http://php.net/manual/en/book.imagick.php).

Currently supported:
* BLP1

Example Usage
==========

```php
<?php

require 'blp.php';

try {
    $fname = 'bin/war3mapMap.blp';
    $blpfile = new BLPImage($fname);
    
    // get imagick handle
    $image = $blpfile->image();

    // convert to jpeg
    $image->setImageFormat("jpeg");
    $image->writeImage('bin/output.jpg');

    // convert to png
    $image->setImageFormat("png");
    $image->writeImage('bin/output.png');

    // display the image
    header("Content-Type: image/png");
    echo $image->getImageBlob();

    $blpfile->close();


} catch (Exception $e) {
    echo 'BLPImage Exception: ',  $e->getMessage(), "\n";

    exit;
}

?>
```
==========

Thanks to [Dr Super Good](https://github.com/DrSuperGood) for help with bug fixes.