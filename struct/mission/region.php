<?php

namespace struct\mission;

class region
{
    public $id;
    public $pos1;
    public $pos2;
    public $pos3;
    public $pos4;
    public $name;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
