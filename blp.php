<?php
/*
    Created by TriggerHappy
*/

require_once __DIR__ . '/blp.reader.php';

class BLPImage
{

    private $filename, $file, $filesize, $stream;
    private $compression, $flags, $width, $height, $type, $mipmapOffset, $mipmapSize;
    private $image, $imageData;

    function __construct($path)
    {
        if (!file_exists($path))
        {
            throw new Exception('File doesn\'t exist.');
        }

        $this->filename = $path;
        $this->filesize = filesize($path);
        $this->file = fopen($path, 'rb');
        $this->stream = new BLPReader($this->file);

        if (!$this->parseHeader())
        {
            throw new Exception('Invalid image header.');
        }
    }

    function __destruct() 
    {   
        $this->close();
    }

    function close() 
    {
        if ($this->file && get_resource_type($this->file) == 'stream') fclose($this->file); 
        if ($this->image) $this->image->clear();
    }

    function image()
    {
        return $this->image;
    }

    private function parseHeader()
    {
        $valid_header = false;

        $this->stream->setPosition(0);

        while (!$valid_header && $this->stream->fp < $this->filesize)
        {
            $buffer = $this->stream->readBytes(4);

            if ($buffer == "BLP1")
            {
                // parse header
                $this->compression  = $this->stream->readUInt32();
                $this->flags        = $this->stream->readUInt32();
                $this->width        = $this->stream->readUInt32();
                $this->height       = $this->stream->readUInt32();
                $this->type         = $this->stream->readUInt32();
                $subtype            = $this->stream->readUInt32();

                // load mipmap data
                $mipmaps = 0;
                for($i=0; $i < 16; $i++)
                {
                    $this->mipmapOffset[$i] = $this->stream->readUInt32();
                }

                for($i=0; $i < 16; $i++)
                {
                    $this->mipmapSize[$i] = $this->stream->readUInt32();

                    if ($this->mipmapSize[$i] > 0)
                    {
                        $mipmaps += 1;
                    }
                }

                // check if jpeg or palleted blp
                switch($this->compression)
                {
                    default:
                    case '0': // jpeg
                        $jpeg_start         = $this->stream->fp;
                        $jpeg_header_size   = $this->stream->readUInt32();
                        $jpeg_header        = $this->stream->readBytes($jpeg_header_size);

                        $this->stream->setPosition($this->mipmapOffset[0]);
                        $this->imageData = $this->stream->readBytes($this->mipmapSize[0]);

                        $this->image = new Imagick();
                        $this->image->readImageBlob($jpeg_header . $this->imageData);
                        $this->image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
                        $this->image = BLPImage::BGR2RGB($this->image); // swap red and blue

                        break;

                    case '1': // palleted
                        $colors = array();

                        if ($this->type < 3 || $this->type > 5)
                        {
                            throw new Exception('Unknown picture type ' . $this->type . '.');
                        }

                        $im = imagecreate($this->width, $this->height);

                        // read color pallete (BGR)
                        for($i=0; $i<256; $i++)
                        {
                            $b = $this->stream->readInt();
                            $g = $this->stream->readInt();
                            $r = $this->stream->readInt();
                            $a = $this->stream->readInt();

                            // store it (RGB)
                            $colors[] = imagecolorallocate($im, $r, $g, $b);
                        }

                        // store pixel color data
                        $index_list = array();
                        $alpha_list = array();

                        $size = $this->width * $this->height;

                        for($i=0; $i<$size; $i++)
                        {
                            $index_list[] = $this->stream->readInt();
                        }

                        if ($this->type == 5)
                        {
                            for($i=0; $i<$size; $i++)
                            {
                                $alpha_list[] = $this->stream->readInt();
                            }
                        }

                        // write color data to image
                        $width  = $this->width;
                        $height = $this->height;
                        $color_index = 0;

                        for ($y = 1; $y < $height; $y++)
                        {
                            for ($x = 0; $x < $width; $x++)
                            {
                                $color_index += 1;

                                imagesetpixel($im, $x, $y, $colors[$index_list[$color_index]]);
                            }
                        }

                        // create a temporary file which we can pass to imagick.
                        $temp_file = tempnam(sys_get_temp_dir(), 'blp');
                        imagepng($im, $temp_file);

                        $this->image = new Imagick($temp_file);
                        
                        imagedestroy($im);
                        unlink($temp_file);

                        break;

                }

                $valid_header = true;
            }
        }

        return $valid_header;
    }

    private static function BGR2RGB($image) {
        $red = clone $image;
        $red->separateImageChannel(Imagick::CHANNEL_BLUE);

        $green = clone $image;
        $green->separateImageChannel(Imagick::CHANNEL_GREEN);

        $red->compositeImage($green, Imagick::COMPOSITE_COPYGREEN, 0, 0);

        $green = null;

        $blue = clone $image;
        $blue->separateImageChannel(Imagick::CHANNEL_RED);

        $red->compositeImage($blue, Imagick::COMPOSITE_COPYBLUE, 0, 0);

        return $red;
    }

}

?>