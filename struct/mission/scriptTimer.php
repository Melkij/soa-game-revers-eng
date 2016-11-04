<?php

namespace struct\mission;

class scriptTimer
{
    public $id;
    public $unknown;
    public $name;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
