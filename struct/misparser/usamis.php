<?php

namespace struct\misparser;

use struct\mission\mapobject;

class usamis extends normal
{
    public static function versionMarker()
    {
        return '05 00 00 00 02 00 00 00 03 00 00 00';
    }

    protected function partyParser2() {}
    protected function authorBlock() {}

    protected function fourByteMarker()
    {
        $this->nextEqualHex('08 00 00 00');
    }

    protected function fourByteMarker2()
    {
        $this->nextEqualHex('01 00 00 00');
    }

    protected function scriptsAreaParser()
    {
        $this->regions();
        $this->nextEqualHex('01 00 00 00 00 00 00 00 00 00 00 00 07 00 00 00'); // no timers
        $this->nextEqualHex('00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00'); // no scripts
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00'); // no switchs and pings
    }

    protected function endArea()
    {
        $this->nextEqualHex('64 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00');
    }

    protected function objectHeaderConst()
    {
        $this->nextEqualHex('01 01 00 00 00 00 08 00 00 00 00 00 00 00');
    }

    protected function objectHeaderBlock2(mapobject $obj)
    {
    }

    protected function concreteObjectParser(mapobject $obj)
    {
        if ($obj->type >= 1000 and $obj->type < 1100) {
            $this->nextEqualHex('00 00 00 00');
            $this->objectLandscapeMapVersionSpecific();
            return $obj->reinitAsLandscape();
        }

        if ($this->file->hexahead(8) == '00 00 00 00 00 00 00 00') {
            $this->nextEqualHex('00 00 00 00 00 00 00 00');
            return $obj->reinitAsBuild();
        }

        throw new ParserError('not implement');
    }

    protected function mapEnvironment()
    {
        $this->mis->landId = $this->int32();
        $this->nextEqualHex('ff ff ff ff 01 00 00 00');

        // где находится камера
        $this->mis->editorCamPosWestEast = $this->float(); // при движении на запад уменьшается, скорей всего 0 - крайняя западная точка
        $this->mis->editorCamPosNorthSouth = $this->float(); // 0 в крайнем севере
        $this->mis->editorCamPosHeight = $this->float(); // 0 уровень моря, чем больше 0 - тем выше
        // куда камера развёрнута
        $this->mis->editorCamViewDirectionX = $this->float(); // явно координаты взгляда, мог напутать x и y
        $this->mis->editorCamViewDirectionY = $this->float();
        $this->mis->editorCamViewDirectionZ = $this->float(); // но это точно z

        $this->mis->unknownCamSeparator = $this->unknownBlock(4);

        $this->unknownblock(40);

        $this->nextEqualHex('00 00 00 00 00 00 00 00');

        $this->nextEqualHex('04 00 00 00');

        $this->mis->skyId = $this->int32();
        $this->mis->unknownBlockAfterSky = $this->unknownBlock(4);
        $this->mis->rainPercent = $this->float();
        $this->mis->temperature = $this->float();
        $this->mis->unknownBlockAfterTemperature = $this->unknownBlock(1);
        $this->nextEqualHex('00 00 00 09 00 00 00');
        $this->mis->gametime = $this->unknownBlock(4); // скорей всего здесь игровое время +- возможное смещение на 1 байт
    }

    protected function blockBeforeObjects()
    {
        $this->nextEqualHex('25 00 00 00 00 00 00 00 00 00 00 00');
    }
}

