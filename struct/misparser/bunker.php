<?php

namespace struct\misparser;

use struct\mission\party;
use struct\mission\region;
use struct\mission\mapobject;
use struct\mission\objects\activeobject;
use struct\mission\objects\human;
use struct\mission\objects\vehicle;
use struct\mission\ammonition\weapon;
use struct\mission\ammonition\weaponGranate;
use struct\mission\ammonition\weaponGranateLauncher;
use struct\mission\ammonition\other;
use struct\mission\ammonition\armor;
use struct\mission\ammonition\ammonition;

class bunker extends normal
{
    public static function versionMarker()
    {
        return '06 00 00 00 02 00 00 00 04 00 00 00';
    }

    protected function partyParser2()
    {
        $this->nextEqualHex('00');
    }

    protected function authorBlock() {}

    protected function fourByteMarker2()
    {
        $this->nextEqualHex('02 00 00 00');
    }

    protected function mapEnvironment()
    {
        $this->mis->landId = $this->int32();
        $this->nextEqualHex('ff ff ff ff 02 00 00 00');

        // где находится камера
        $this->mis->editorCamPosWestEast = $this->float(); // при движении на запад уменьшается, скорей всего 0 - крайняя западная точка
        $this->mis->editorCamPosNorthSouth = $this->float(); // 0 в крайнем севере
        $this->mis->editorCamPosHeight = $this->float(); // 0 уровень моря, чем больше 0 - тем выше
        // куда камера развёрнута
        $this->mis->editorCamViewDirectionX = $this->float(); // явно координаты взгляда, мог напутать x и y
        $this->mis->editorCamViewDirectionY = $this->float();
        $this->mis->editorCamViewDirectionZ = $this->float(); // но это точно z

        $this->mis->unknownCamSeparator = $this->unknownBlock(4);

        $this->nextEqualHex('00 00 00 00 00 00 00 00');

        $this->nextEqualHex('05 00 00 00');

        $this->mis->skyId = $this->int32();
        $this->mis->unknownBlockAfterSky = $this->unknownBlock(4);
        $this->mis->rainPercent = $this->float();
        $this->mis->temperature = $this->float();
        $this->mis->unknownBlockAfterTemperature = $this->unknownBlock(1);
        $this->nextEqualHex('00 00 00 00 0b 00 00 00');
        $this->mis->gametime = $this->unknownBlock(4); // скорей всего здесь игровое время +- возможное смещение на 1 байт
    }

    protected function blockBeforeObjects()
    {
        $this->nextEqualHex('41 00 00 00');
    }

    protected function concreteObjectParser(mapobject $obj)
    {
        if ($obj->type >= 1000 and $obj->type < 1100) {
            $this->nextEqualHex('00 00 00 00');
            $this->objectLandscapeMapVersionSpecific();
            //$this->assertEquals('00 00 00 00', $obj->unknown0);
            return $obj->reinitAsLandscape();
        }

        if ($this->file->hexahead(16) == '00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00') {
            $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            //$this->assertEquals('00 00 00 00', $obj->unknown0);
            return $obj->reinitAsBuild();
        }

        $this->nextEqualHex('00 00 00 00 00 00 00 00 ff ff ff ff ff ff ff ff');
        return $this->mapObjectActive($obj->reinitAsActiveObject());
    }

    protected function mapObjectActive(activeobject $obj)
    {
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00');
        $obj->unknownActive0 = $this->unknownblock(1);
        $this->nextEqualHex('40 42 0f 00 00 00 00 00');
        $weaponsCount = $this->int32();
        for ($structCounter = 0; $structCounter < $weaponsCount; ++$structCounter) {
            // судя по всему штатное вооружение машин. Но присутствует и для людей
            $this->ammonitionParser([2,66], $obj);
        }

        $ammoCount = $this->int32();
        for ($ammo = 0; $ammo < $ammoCount; ++$ammo) {
            $obj->addAmmunitionItem($this->ammonitionParser(null, $obj));
        }

        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00');

        $obj->unknownActive1 = $this->unknownblock(2);
        $this->nextEqualHex('ff 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
        $this->nextEqualHex('00');

        // грубый хак урала на карте
        $unit = $obj->reinitAsVehicle();
        $this->mapObjectVehicle($unit);
        return $unit;
    }

    protected function mapObjectVehicle(vehicle $obj)
    {
        $this->nextEqualHex('00 00 00 00 80 bf 00 00 80 bf 00 00 00 00 00 00 00 00 00 00 00');
        $obj->unknownVehicle0 = $this->unknownblock(1);
        $this->nextEqualHex('00 00 80 bf 00 00 80 bf');
        $obj->unknownVehicle1 = $this->unknownblock(24);
        $this->nextEqualHex('00 00 00 00 00');
        $obj->unknownVehicle2 = $this->unknownblock(2);
        $this->nextEqualHex('00 00');
        $obj->unknownVehicle3 = $this->unknownBlock(1); // возможно метка, есть ли люди внутри
        $obj->unknownVehicle4 = $this->unknownBlock(20);
        $obj->unknownVehicle5 = $this->unknownblock(1);
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');

        $selectableWeaponsCount = $this->int8();
        for ($i = 0; $i < $selectableWeaponsCount; ++$i) {
            $obj->maybeSelectableWeapon[] = [
                $this->int32(), // наверное, id позиции оружия
                $this->int32(), // uid оружия?
            ];
        }

        $this->nextEqualHex('00 00 00 00 00 00 80 3f 00 00 00 00 00');
        if (in_array($obj->type, [2, 10, 22])) {
            $this->nextEqualHex('00 00 00 00 00');
        }

        if ($obj->posZ === 0.0) {
            $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 f0 55 00 00 0a d7 23 3c 40 08 f4 34 00 00 00 00 01 00');
            $obj->unknownVehicle6 = $this->unknownblock(13);
            $this->nextEqualHex('00 00 80 3f f0 55 00 00 0a d7 23 3c 40 08 f4 34 00 00 00 00 00 00 00 00 00 00 40 42 0f 00 00 00 00 00 00 00 00 80 40 00 00 00 00');
        }
    }

    protected function scriptsAreaParser()
    {
        $this->regions();
        $this->nextEqualHex('01 00 00 00 00 00 00 00 00 00 00 00 15 00 00 00'); // no timers
        $this->nextEqualHex('00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00'); // no scripts
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00'); // no switchs and pings
        // constant endfile
        $this->nextEqualHex('64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00');
        $this->nextEqualHex('02 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00');
    }

    protected function endArea() {}

    protected function objectHeaderBlock1(mapobject $obj)
    {
        $this->nextEqualHex('01 01 00 00 00 00 10 00 00 00 00 00 00 00');
    }

    protected function objectHeaderBlock2(mapobject $obj) {}
    protected function objectLandscapeMapVersionSpecific()
    {
        $this->nextEqualHex('00 00 00 00 00 00 00 00');
    }
}
