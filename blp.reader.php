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
        fseek($this->file, $fp, SEEK_SET);
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
        return unpack("C*", $this->readByte())[1];
    }

    public function readUInt8() 
    {
        return unpack("c", $this->readByte())[1];
    }

    public function readUInt32() 
    {
        return unpack("V", $this->readBytes(4))[1];
    }   

}

?>