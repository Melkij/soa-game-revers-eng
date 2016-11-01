<?php

namespace struct\mission;

use struct\mission\objects\landscape;
use struct\mission\objects\build;
use struct\mission\objects\ammunition;
use struct\mission\objects\ammunitionBox;
use struct\mission\objects\activeobject;

class mapobject
{
    public $type;
    public $mapuid;
    public $posX;
    public $posY;
    public $rotate;
    public $scale;
    public $maxHealth;
    public $currentHealth;
    public $maxArmor;
    public $currentArmor;
    public $baseObjectName;

    public $unknown0; // для нормальной карты - только 00 00 00 00 или 00 00 00 80
    public $unknown1;
    public $unknown2;

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }

    protected function reinit(mapobject $new)
    {
        foreach (get_object_vars($this) as $var => $value) {
            $new->{$var} = $value;
        }
        return $new;
    }

    /**
     * деревья, камни
     */
    public function reinitAsLandscape()
    {
        return $this->reinit(new landscape);
    }

    public function reinitAsAmmunition()
    {
        return $this->reinit(new ammunition);
    }
    public function reinitAsAmmunitionBox()
    {
        return $this->reinit(new ammunitionBox);
    }
    public function reinitAsActiveObject()
    {
        return $this->reinit(new activeobject);
    }

    public function reinitAsBuild()
    {
        if (abs($this->scale - 1) > 0.0001) {
            throw new \LogicException('can not scale building');
        }
        return $this->reinit(new build);
    }
}
