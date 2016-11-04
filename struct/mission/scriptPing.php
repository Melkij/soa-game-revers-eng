<?php

namespace struct\mission;

class scriptPing
{
    public $id;
    public $name;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
