<?php

namespace struct\mission;

class texture
{
    public $id;
    public $posX;
    public $posY;
    public $sizeX;
    public $sizeY;
    public $rotate;
    public $unknownBlock;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
