<?php

namespace struct\misparser;

use struct\mission\mapobject;

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

    protected function afterRegionsBlock()
    {
        $this->nextEqualHex('01 00 00 00 00 00 00 00 00 00 00 00 15 00 00 00');
    }

    protected function endFile()
    {
        $this->nextEqualHex('64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00');
        $this->nextEqualHex('02 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00');
    }

    protected function objectHeaderConst()
    {
        $this->nextEqualHex('01 01 00 00 00 00 10 00 00 00 00 00 00 00');
    }

    protected function objectHeaderBlock2(mapobject $obj) {}
    protected function objectLandscapeMapVersionSpecific()
    {
        $this->nextEqualHex('00 00 00 00 00 00 00 00');
    }
}
