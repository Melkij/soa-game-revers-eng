<?php

namespace struct;

class BinaryFile
{
    private $file = '';
    private $position = 0;
    private $fileSize = 0;

    public static function readFile($path)
    {
        return new self(file_get_contents($path));
    }

    private function __construct($content = '')
    {
        $this->file = $content;
        $this->fileSize = mb_strlen($this->file, 'binary');
    }

    public function reset()
    {
        $this->position = 0;
    }

    private function binhex($input, $sep = ' ') {
        $hex = '';
        foreach (str_split($input,1) as $w) {
            $hex .= bin2hex($w).$sep;
        }
        return rtrim($hex);
    }

    public function readaheadbyte($len)
    {
        if (($this->position + $len) > $this->getMaxPosition()) {
            throw new \LogicException('unable read file after eof!');
        }
        return mb_substr($this->file, $this->position, $len, 'binary');
    }

    public function hexahead($len)
    {
        return $this->binhex($this->readaheadbyte($len));
    }

    public function hexread($len)
    {
        return $this->binhex($this->readbyte($len));
    }

    public function readbyte($len)
    {
        $bytes = $this->readaheadbyte($len);
        $this->position += $len;
        return $bytes;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function getMaxPosition()
    {
        return $this->fileSize;
    }

    /**
     * testcase: 99 99 19 3f ~ 0,6
     */
    public function float()
    {
        return current(unpack('f', $this->readbyte(4)));
    }
    public function int32()
    {
        return current(unpack('V', $this->readbyte(4)));
    }
    public function int8()
    {
        return ord($this->readbyte(1));
    }

    public function utf8Text($len)
    {
        return iconv('utf8', 'utf8//IGNORE', $this->readbyte($len)); // utf to utf iconv to verify utf binary
    }

    public function skip($len)
    {
        $this->position += $len;
    }

    public function isEof()
    {
        return $this->position === $this->fileSize;
    }
}
