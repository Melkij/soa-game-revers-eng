<?php

namespace struct\mission\objects;
use struct\mission\mapobject;

/**
 * звери, чел овечки, машинки
 */
class activeobject extends mapobject
{
    public $unknownActive0;
    public $unknownActive1;
    public $unknownActive2;

    public $weapons = [];

    public function reinitAsAnimal()
    {
        return $this->reinit(new animal);
    }
    public function reinitAsVehicle()
    {
        return $this->reinit(new vehicle);
    }
    public function reinitAsHuman()
    {
        return $this->reinit(new human);
    }
}
