<?php

namespace struct\mission;

class party
{
    const AI_TYPE_HUMAN = 'human';
    const AI_TYPE_PC = 'pc';

    public $aitype;
    public $unknownBlockFirst = '';
    public $seekerInventory = [];
    public $color;
    public $startPositionX;
    public $startPositionY;
    public $name;

    public $unknownIntPairs;
    public $maybeStartCamPos;
    public $unknownParser3Block;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
