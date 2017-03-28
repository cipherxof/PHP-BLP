# PHP-BLP
Blizzard BLP Image File Parser for PHP.

Requires [GD](http://php.net/manual/en/book.image.php) & [ImageMagick](http://php.net/manual/en/book.imagick.php).

Currently supported:
* BLP0 (Reign of Chaos Beta)
* BLP1 (All other War3 versions)

Basic Example
==========

```php
<?php

require 'blp.php';

$blp_image = new BLPImage('war3mapMap.blp');

$blp_image->saveAs("war3mapMap.png");
$blp_image->saveAs("war3mapMap.jpg");

?>
```

Advanced Example
==========

```php
<?php

require 'blp.php';

try {
    $fname = 'war3mapMap.blp';
    $blpfile = new BLPImage($fname);
    
    // get imagick handle
    $image = $blpfile->image();

    // convert to jpeg
    $image->setImageFormat("jpeg");
    $image->writeImage('output.jpg');

    // convert to png
    $image->setImageFormat("png");
    $image->writeImage('output.png');

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

Thanks to [Dr Super Good](https://github.com/DrSuperGood) for his [BLP Specifications](https://www.hiveworkshop.com/threads/blp-specifications-wc3.279306/) and help with bug fixes.