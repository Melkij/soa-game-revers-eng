<?php

namespace struct\mission;

class scriptSwitch
{
    public $id;
    public $name;
    public $isOn;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
