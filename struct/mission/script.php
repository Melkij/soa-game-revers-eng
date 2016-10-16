<?php

namespace struct\mission;

class script
{
    public $unknown1 = [];
    public $unknown2 = [];
    public $unknownBody;
    public $unknown3 = [];
    public $names = [];
    public $unknown4 = [];

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
