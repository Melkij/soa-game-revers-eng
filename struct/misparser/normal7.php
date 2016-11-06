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

/*
 * оригинальные миссии 5a,5b,9a
 *
 * есть ошибки в объектах
 */
class normal7 extends normal
{
    public static function versionMarker()
    {
        return '0a 00 00 00 02 00 00 00 07 00 00 00';
    }

    protected function authorBlock() {}

    protected function blockBeforeObjects()
    {
        $this->nextEqualHex('60 00 00 00');
    }
}
