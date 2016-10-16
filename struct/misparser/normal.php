<?php

namespace struct\misparser;

use struct\mission\party;
use struct\mission\region;
use struct\mission\mapobject;

class normal extends base
{
    public static function versionMarker()
    {
        return '0a 00 00 00 02 00 00 00 0a 00 00 00';
    }

    final protected function process()
    {
        $this->nextEqualHex('38 f9 b3 0a 62 93 d1 11 9a 2b 08 00 00 30 05 12');
        $this->nextEqualHex(static::versionMarker());
        $this->mis->title = $this->text();
        $this->nextEqualHex('00');
        $this->mis->description = $this->text();
        $this->mis->mapwidth = $this->int32();
        $this->mis->mapheight = $this->int32();

        $this->partyParser1();
        $this->partyParser2();

        $this->authorBlock();
        $this->fourByteMarker();

        $this->assertEquals($this->mis->mapwidth, $this->int32());
        $this->assertEquals($this->mis->mapheight, $this->int32());

        $this->heightsMap();
        $this->nextEqualHex('02 00 00 00');
        $this->textures();
        $this->mapEnvironment();
        $this->nextEqualHex('02 00 00 00');

        $this->partyParser3();

        $this->blockBeforeObjects();

        $this->objects();
        $this->nextEqualHex('01 00 00 00');
        $this->regions();
        $this->afterRegionsBlock();
        $this->scripts();
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
        $this->endFile();
        if (! $this->file->isEof()) {
            throw new ParserError('not eof!');
        }
    }

    protected function partyParser1()
    {
        $partyCount = $this->int32();
        $this->nextEqualHex('00');

        for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
            $party = $this->mis->addParty($partyId);
            $party->aitype = $this->getPartyAiType($this->int32());

            $len = $this->file->int8();
            if ($len) {
                $party->unknownBlockFirst = $this->unknownBlock($len);
            }
        }
    }

    final protected function getPartyAiType($aiType)
    {
        $aiTypes = [
            1 => party::AI_TYPE_HUMAN,
            2 => party::AI_TYPE_PC,
        ];

        if (isset($aiTypes[ $aiType ])) {
            return $aiTypes[ $aiType ];
        } else {
            throw new ParserError('unknown ai type '.$aiType);
        }
    }

    protected function partyParser2()
    {
        $this->nextEqualHex('00 00');
        $partyCount = $this->int32();
        $this->assertEquals($this->mis->getPartyCount(), $partyCount);

        for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
            $party = $this->mis->getParty($partyId);
            $seekerInventoryCount = $this->int32();
            $party->color = $this->int32();
            for ($i = 0; $i < $seekerInventoryCount; ++$i) {
                $party->seekerInventory[] = $this->unknownBlock(5*4);
            }
        }
        $this->nextEqualHex('00');
    }

    protected function partyParser3()
    {
        $partyCount = $this->int32();
        $this->assertEquals($this->mis->getPartyCount(), $partyCount);

        for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
            $party = $this->mis->getParty($partyId);

            $this->assertEquals($party->aitype, $this->getPartyAiType($this->int32()));
            $this->nextEqualHex('0c 00 00 00');
            $this->assertEquals($partyId, $this->int32());
            $party->startPositionX = $this->float(); // координаты флажка-старта при переходе между миссиями
            $party->startPositionY = $this->float();
            $party->name = $this->text();

            $pairsCount = $this->int32();
            if ($pairsCount) {
                $party->unknownIntPairs = $this->unknownBlock(4*2*$pairsCount);
            }

            $party->maybeStartCamPos = $this->unknownBlock(1 + 7*4);
            $seekerInventoryCount = $this->int32(); // again?
            $this->assertEquals(count($party->seekerInventory), $seekerInventoryCount);
            for ($i = 0; $i < $seekerInventoryCount; ++$i) {
                $this->assertEquals($party->seekerInventory[ $i ], $this->file->hexread(5*4));
            }
            $this->nextEqualHex('01 01');
            $party->unknownParser3Block = $this->unknownBlock(1); // отзывается на вооружённых рыцарей? diff female_enemy_knight_skin.mis female_enemy_knight_empty.mis
            $this->nextEqualHex('00 00 00 00 00 00 00');
        }
    }

    protected function authorBlock()
    {
        $this->mis->editDate = date('Y-m-d H:i:s', $this->int32());
        $this->mis->author = $this->text();
        $this->nextEqualHex('00 00 00 00');
    }

    protected function fourByteMarker()
    {
        $this->nextEqualHex('09 00 00 00');
    }

    protected function heightsMap()
    {
        // странная штука. Смог распарсить, как пропустить этот блок, но не понять, как он закодирован
        while($entrysize = $this->int32()) {
            // странная структура, не могу уловить смысл значений
            $this->mis->heightsMap1[] = $this->unknownBlock($entrysize);
        }
        while($entrysize = $this->int32()) {
            $block = $this->unknownBlock($entrysize);
            $this->mis->heightsMap1[] = $block;
            if ($entrysize == 48) {
                // статична для этой длины?
                $this->assertEquals('78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01', $block);
            }
        }
    }

    protected function textures()
    {
        $texturesCount = $this->int32();
        for ($i = 0; $i < $texturesCount; ++$i) {
            $texture = $this->mis->addTexture();
            // наложение текстуры одной поверх другой определяется позицией в файле
            // каждая следующая текстура кладётся поверх предыдущих
            $texture->posX = $this->float(); // может, наоборот y
            $texture->posY = $this->float();
            // размеры могут быть отрицательными - если текстура инвертирована по этой оси
            $texture->sizeX = $this->float();
            $texture->sizeY = $this->float();
            $texture->rotate = $this->float();
            $this->nextEqualHex('ff ff ff ff ff ff ff ff ff ff ff ff ff ff ff ff');
            $texture->id = $this->int32();
            $texture->unknownBlock = $this->unknownBlock(4);
        }
    }

    protected function mapEnvironment()
    {
        $this->mis->landId = $this->int32();
        $this->nextEqualHex('ff ff ff ff 03 00 00 00');

        $camBlock = $this->file->hexahead(6*4);
        // где находится камера
        $this->mis->editorCamPosWestEast = $this->float(); // при движении на запад уменьшается, скорей всего 0 - крайняя западная точка
        $this->mis->editorCamPosNorthSouth = $this->float(); // 0 в крайнем севере
        $this->mis->editorCamPosHeight = $this->float(); // 0 уровень моря, чем больше 0 - тем выше
        // куда камера развёрнута
        $this->mis->editorCamViewDirectionX = $this->float(); // явно координаты взгляда, мог напутать x и y
        $this->mis->editorCamViewDirectionY = $this->float();
        $this->mis->editorCamViewDirectionZ = $this->float(); // но это точно z

        $this->mis->unknownCamSeparator = $this->unknownBlock(8);

        $this->assertEquals($camBlock, $this->file->hexread(6*4));

        $this->nextEqualHex('00 00 00 00 00 00 00 00');
        $this->mis->minimapsize = $this->float(); // множитель масштаба миникарты. Дефолт 00 00 80 3F (т.е. 1), число меньше - карта ближе, больше - дальше

        $this->nextEqualHex('05 00 00 00');

        $this->mis->skyId = $this->int32();
        $this->mis->unknownBlockAfterSky = $this->unknownBlock(4);
        $this->mis->rainPercent = $this->float();
        $this->mis->temperature = $this->float();
        $this->mis->unknownBlockAfterTemperature = $this->unknownBlock(1);
        $this->nextEqualHex('00 00 00 00 0c 00 00 00');
        $this->mis->gametime = $this->unknownBlock(4); // скорей всего здесь игровое время +- возможное смещение на 1 байт
    }

    protected function blockBeforeObjects()
    {
        $this->nextEqualHex('63 00 00 00');
    }

    protected function objects()
    {
        $objectsCount = $this->int32();
        for ($i = 0; $i < $objectsCount; ++$i) {
            $objectTypeId = $this->int32();
            $uid = $this->int32();
            $obj = new mapobject;
            $obj->type = $objectTypeId;
            $obj->posX = $this->float();
            $obj->posY = $this->float();
            $obj->unknown0 = $this->unknownBlock(4);
            $obj->rotate = $this->float();
            $obj->scale = $this->float(); // размер камней, деревьев. 1 для всех остальных
            $this->objectHeaderConst();
            $obj->unknown1 = $this->unknownBlock(1);
            $this->nextEqualHex('00 00 00 01');
            $obj->maxHealth = $this->int32();
            $obj->currentHealth = $this->int32();
            $obj->maxArmor = $this->int32();
            $obj->currentArmor = $this->int32();
            $this->nextEqualHex('ff ff ff ff');
            $this->objectHeaderBlock2($obj);

            $object = $this->concreteObjectParser($obj);
            $this->mis->addObject($uid, $object);
        }
    }

    protected function concreteObjectParser(mapobject $obj)
    {
        if ($obj->type >= 1000 and $obj->type < 1100) {
            $this->nextEqualHex('00 00 00 00');
            $this->objectLandscapeMapVersionSpecific();
            $this->assertEquals('00 00 00 00', $obj->unknown0);
            return $obj->reinitAsLandscape();
        }

        if ($this->file->hexahead(16) == '00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00') {
            $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $this->assertEquals('00 00 00 00', $obj->unknown0);
            return $obj->reinitAsBuild();
        }

        throw new ParserError('not implement');
    }

    protected function objectLandscapeMapVersionSpecific() {}

    protected function objectHeaderConst()
    {
        $this->nextEqualHex('01 01 00 00 00 00 16 00 00 00 00 00 00 00');
    }

    protected function objectHeaderBlock2(mapobject $obj)
    {
        $this->nextEqualHex('00 00 00 00');
        $obj->unknown2 = $this->unknownBlock(4);
        $this->nextEqualHex('00 00 80 3f 00 00 00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff');
        $obj->baseObjectName = $this->text();
        $this->nextEqualHex('ff ff ff ff 00 00 00 00 00 00 00 00');
    }

    protected function regions()
    {
        $count = $this->int32();
        for ($i = 0; $i < $count; ++$i) {
            $region = new region;
            $region->id = $this->int32();
            // очевидно позиции двух диагональных углов прямоугольника
            $region->pos1 = $this->float();
            $region->pos2 = $this->float();
            $region->pos3 = $this->float();
            $region->pos4 = $this->float();
            $region->name = $this->text();
            $this->mis->addRegion($region);
        }
    }

    protected function afterRegionsBlock()
    {
        $this->nextEqualHex('02 00 00 00 00 00 00 00 00 00 00 00 24 00 00 00');
    }

    protected function scripts()
    {
        $scriptsCount = $this->int32();

        if (! $scriptsCount) {
            $this->nextEqualHex('00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00');
            return;
        }

        // базовый парсер, умеет разбирать пустые скрипты и не ломается с триггером mission_start

        for ($i = 0; $i < $scriptsCount; ++$i) {
            $script = $this->mis->addScript();
            for ($i = 0; $i < $scriptsCount; ++$i) {
                $script->unknown1[] = $this->unknownBlock(4);
                $this->assertEquals($i + 1, $this->int32());
            }
            for ($i = 0; $i < $scriptsCount; ++$i) {
                $this->nextEqualHex('00');
            }
            $blockCount = $this->int32();
            $this->assertEquals($scriptsCount*2, $blockCount);
            for ($i = 0; $i < $blockCount; ++$i) {
                $script->unknown2[] = $this->unknownBlock(9);
            }
            $this->nextEqualHex('00 00 00 00 01 00 00 00 01 00 00 00 01 00 00 00');
            $script->unknownBody = $this->unknownBlock(24 + 36 * ($scriptsCount-1));
            $nameBlockCount = $this->int32();
            for ($i = 0; $i < $nameBlockCount; ++$i) {
                $this->nextEqualHex('01 00 00 00');
                $script->unknown3[] = $this->unknownBlock(4);
                $this->nextEqualHex('01 00 00 00 d0 07 00 00 01 00 00 00 00 00 00 00');
                $script->names[] = $this->text();
            }
            $unknownBlockLen = $this->int32();
            for ($i = 0; $i < $unknownBlockLen; ++$i) {
                $script->unknown4[] = $this->unknownBlock(20);
            }
        }
    }

    protected function endFile()
    {
        $this->nextEqualHex('64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00');
        $this->nextEqualHex('07 00 00 00');

        if ($this->mis->getScriptsCount()) {
            $this->nextEqualHex('01 00 00 00');
        } else {
            $this->nextEqualHex('00 00 00 00');
        }

        $this->nextEqualHex('00 00 00 00 02 00 00 00 00 00 00 00');
        $this->mis->unknownBlockEnding = $this->unknownBlock(1);
        $this->nextEqualHex('00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
    }
}
