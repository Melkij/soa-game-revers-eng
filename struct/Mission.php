<?php

namespace struct;
use struct\mission\party;
use struct\mission\texture;
use struct\mission\script;
use struct\mission\region;
use struct\mission\mapobject;

/**
 * абстрактный контейнер описания миссии
 * цель 1: парсер любого *.mis (допустимо с неизвестными бинарными кусками)
 * цель 2: возможность по этому контейнеру сгенерировать бинарный mis, идентичный исходнику
 * цель 3: создание новой миссии только по описанию в этом классе - т.е. непонятных бинарных кусков не должно быть
 */
class Mission
{
    public static function readFromFile($filename)
    {
        $mis = new Mission;
        misparser\base::load(BinaryFile::readFile($filename), $mis);
        return $mis;
    }

    private function __construct() {}

    public $title;
    public $description;
    public $author;
    public $mapwidth;
    public $mapheight;
    public $editDate;

    public $landId;
    public $skyId;
    public $rainPercent;
    public $temperature;
    public $gametime;

    // камера в редакторе, позиция
    public $editorCamPosWestEast;
    public $editorCamPosNorthSouth;
    public $editorCamPosHeight;
    // куда камера развёрнута
    public $editorCamViewDirectionX;
    public $editorCamViewDirectionY;
    public $editorCamViewDirectionZ;

    public $unknownCamSeparator;
    public $unknownBlockAfterSky;
    public $unknownBlockAfterTemperature;
    public $unknownBlockEnding;

    public $minimapsize; // или только редактора или ещё дефолтный на карте

    public $heightsMap1 = [];
    public $heightsMap2 = [];

    public $cdTrackInMission = []; // CD Track 2.mp3
    public $ambienteTags = []; // ambiente_tag.mp3

    protected $textures = [];
    protected $objects = [];
    protected $regions = [];
    protected $scripts = [];
    protected $parties = [];

    public function addParty($id)
    {
        if (isset($this->parties[ $id ])) {
            throw new \LogicException('Duplicate party '.$id);
        }

        $party = new party;
        $this->parties[ $id ] = $party;
        return $party;
    }

    public function getParty($i)
    {
        if (! isset($this->parties[ $i ])) {
            throw new \LogicException('missed party '.$i);
        }
        return $this->parties[ $i ];
    }

    public function getPartyCount()
    {
        return count($this->parties);
    }

    public function addRegion(region $region)
    {
        if (isset($this->regions[ $region->id ])) {
            throw new \LogicException('duplicate region '.$region->id);
        }
        $this->regions[ $region->id ] = $region;
    }

    public function addTexture()
    {
        $texture = new texture;
        $this->textures[] = $texture;
        return $texture;
    }

    public function addScript()
    {
        $script = new script;
        $this->scripts[] = $script;
        return $script;
    }

    public function getScriptsCount()
    {
        return count($this->scripts);
    }

    public function addObject($uid, mapobject $obj)
    {
        if (isset($this->objects[ $uid ])) {
            throw new \LogicException('duplicate object uid '.$uid);
        }
        $this->objects[ $uid ] = $obj;
    }

    public function __set($name, $value)
    {
        throw new \LogicException('not allowed');
    }
}
