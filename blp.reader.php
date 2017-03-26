<?php

class BLPReader
{

    public $fp;
    public $file;

    function __construct($openFile) 
    {
        if ($openFile != null && get_resource_type($openFile) == 'file')
        {
            throw new Exception("BLPReader must take a file handle.");
        }

        $this->file = $openFile;
        $this->fp = 0;
    }

    public function setPosition($fp) 
    {
        $this->fp = $fp;
        fseek($this->file, $fp);
    }

    public function readByte() 
    {
        $this->fp++; 

        return fread($this->file, 1);
    }

    public function readBytes($length) 
    {
        $this->fp+=$length;

        return fread($this->file, $length);
    }

    public function readInt() 
    {
        $tmp = unpack("C*", $this->readByte());
        $this->fp += 1;

        return $tmp[1];
    }

    public function readUInt8() 
    {
        $tmp = unpack("c", $this->readByte());
        $this->fp += 1;

        return $tmp[1];
    }

    public function readUInt32() 
    {
        $tmp = unpack("V", $this->readBytes(4));
        $this->fp += 4;

        return $tmp[1];
    }   

}

?>