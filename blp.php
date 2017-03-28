<?php
/*
    Created by TriggerHappy
*/
require_once __DIR__ . '/blp.reader.php';

// constants
const MAGIC_BLP_V0          = "BLP0";
const MAGIC_BLP_V1          = "BLP1";
const MAGIC_BLP_V2          = "BLP2";

const BLP_COMPRESSION_JPEG  = 0;
const BLP_COMPRESSION_NONE  = 1;

const BLP_JPEG_HEADER_SIZE  = 624;

class BLPImage
{
    private $filename, $file, $filesize, $stream;
    private $compression, $flags, $width, $height, $type;
    private $mipmapOffset, $mipmapSize, $hasMipmaps;
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

    public function close() 
    {
        if ($this->file && get_resource_type($this->file) == 'stream') fclose($this->file); 
        if ($this->image) $this->image->clear();
    }

    public function image(){ return $this->image; }
    public function width(){ return $this->width; }
    public function height(){ return $this->width; }
    public function hasMipmaps(){ return $this->hasMipmaps; }
    public function filename(){ return $this->filename; }
    public function filesize(){ return $this->filesize; }

    public function saveAs($filename, $filetype=null)
    {
        if ($filetype == null)
        {
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        }

        $this->image->setImageFormat($filetype);
        $this->image->writeImage($filename);
    }

    private function parseHeader()
    {
        $valid_header = false;
        $this->stream->setPosition(0);

        while (!$valid_header && $this->stream->fp < $this->filesize)
        {
            $buffer = $this->stream->readBytes(4);

            if ($buffer == MAGIC_BLP_V2)
            {
                throw new Exception("BLP2 files are not supported.");
            }

            if ($buffer != MAGIC_BLP_V0 && $buffer != MAGIC_BLP_V1)
            {
                continue;
            }

            // parse header
            $this->compression  = $this->stream->readUInt32();
            $this->alphabits    = $this->stream->readUInt32();
            $this->width        = $this->stream->readUInt32();
            $this->height       = $this->stream->readUInt32();
            $this->type         = $this->stream->readUInt32();
            $this->hasMipmaps   = $this->stream->readUInt32();

            // alphabit is either 1, 4, or 8 otherwise 0 is assumed.
            if ($this->alphabits != 0 && $this->alphabits != 1 && $this->alphabits != 4 && $this->alphabits != 8)
                $this->alphabits = 0;

            // load mipmap data
            if ($buffer == MAGIC_BLP_V1)
            {
                for($i=0; $i < 16; $i++)
                    $this->mipmapOffset[$i] = $this->stream->readUInt32();

                for($i=0; $i < 16; $i++)
                    $this->mipmapSize[$i] = $this->stream->readUInt32();
            }
            else
            {
                $info  = pathinfo($this->filename);
                $name  = basename($info['basename'],'.'.$info['extension']);
                $dir   = $info['dirname'];
                $fname = "$dir/$name.b00";

                if (!file_exists($fname))
                {
                    throw new Exception("BLP0 image is missg a mipmap file.");
                }

                $this->imageData = file_get_contents($fname);
            }

            // check if jpeg or palleted blp
            switch($this->compression)
            {
                default:
                case BLP_COMPRESSION_JPEG: // jpeg
                    // read jpeg header
                    $jpeg_start         = $this->stream->fp;
                    $jpeg_header_size   = $this->stream->readUInt32();

                    if ($jpeg_header_size > BLP_JPEG_HEADER_SIZE || $jpeg_header_size > ($this->filesize - $this->stream->fp))
                    {
                        trigger_error("BLPImage: Unsafe header size ($jpeg_header_size).", E_USER_WARNING);
                    }

                    $jpeg_header = $this->stream->readBytes($jpeg_header_size);

                    // read first mipmap
                    if ($buffer == MAGIC_BLP_V1)
                    {
                        $this->stream->setPosition($this->mipmapOffset[0]);
                        $this->imageData = $this->stream->readBytes($this->mipmapSize[0]);
                    }

                    $this->image = new Imagick();
                    $this->image->readImageBlob($jpeg_header . $this->imageData);
                    $this->image->setColorspace(Imagick::COLORSPACE_SRGB);
                        
                    $this->rebuildWithoutAlpha(); // remove alpha channel
                    $this->image = BLPImage::BGR2RGB($this->image); // swap red and blue

                    break;
                case BLP_COMPRESSION_NONE: // palleted
                    $colors = array();

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

                    if ($this->alphabits == 8)
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

        return $valid_header;
    }

    private function rebuildWithoutAlpha() 
    {
        // seperate each channel
        $red = clone $this->image;
        $red->separateImageChannel(Imagick::CHANNEL_RED);

        $green = clone $this->image;
        $green->separateImageChannel(Imagick::CHANNEL_GREEN);

        $blue = clone $this->image;
        $blue->separateImageChannel(Imagick::CHANNEL_BLUE);

        // create a new blank image
        $this->image->clear();
        $this->image = new Imagick();
        $this->image->newImage($this->width, $this->height, new ImagickPixel('transparent'));

        // apply each channel seperately to the new image
        $this->image->compositeImage($blue,  Imagick::COMPOSITE_COPYBLUE, 0, 0);
        $this->image->compositeImage($green, Imagick::COMPOSITE_COPYGREEN, 0, 0);
        $this->image->compositeImage($red,   Imagick::COMPOSITE_COPYRED, 0, 0);
        $this->image->negateImage(false); 

        // free memory
        $red->clear();
        $green->clear();
        $blue->clear();
    }

    private static function BGR2RGB($image) 
    {
        $red = clone $image;
        $red->separateImageChannel(Imagick::CHANNEL_BLUE);

        $green = clone $image;
        $green->separateImageChannel(Imagick::CHANNEL_GREEN);

        $red->compositeImage($green, Imagick::COMPOSITE_COPYGREEN, 0, 0);
        $green->clear();

        $blue = clone $image;
        $blue->separateImageChannel(Imagick::CHANNEL_RED);

        $red->compositeImage($blue, Imagick::COMPOSITE_COPYBLUE, 0, 0);
        $blue->clear();

        return $red;
    }

}
?>