<?php

namespace struct\mission\objects;

class human extends activeobject
{
    public $inventoryArmor = null;
    public $inventoryBinokle = null;
    public $inventoryWeapon = null;
    public $ammunitionSlots = [];

    public $humanname;
    public $gender;
    public $inUnitUid = null;
    public $inUnitPosition = null;

    public $level;
    public $experience;
    public $skill1 = 0;
    public $skill2 = 0;

    public $humanUnknown0;
    public $humanUnknown1;
    public $humanUnknown2;
    public $humanUnknown3;
    public $humanUnknown4;
    public $humanUnknown5;
    public $humanUnknown6;
    public $humanUnknown7;

    public function isKnight()
    {
        return (3002 == $this->type);
    }
}
