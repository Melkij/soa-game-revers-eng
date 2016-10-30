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
            $this->assertEquals($party->color, $this->int32());
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
            $party->unknownParser3Block = $this->unknownBlock(8); // отзывается на вооружённых рыцарей? diff female_enemy_knight_skin.mis female_enemy_knight_empty.mis
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
            $this->mis->heightsMap2[] = $block;
            // структуры статичны в рамках своей длины?
            switch ($entrysize) {
                case 26:
                    $this->assertEquals('78 da ed c1 01 0d 00 00 00 c2 a0 f7 4f 6d 0f 07 14 00 00 00 f0 6e 10 00 00 01', $block);
                    break;
                case 37:
                    $this->assertEquals('78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 0d 0f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 80 37 03 38 00 00 01', $block);
                    break;
                case 39:
                    $this->assertEquals('78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 0c 1f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 b7 01 40 00 00 01', $block);
                    break;
                case 48:
                    $this->assertEquals('78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01', $block);
                    break;
                case 51:
                    $this->assertEquals('78 da ed c1 81 00 00 00 00 c3 a0 f9 53 1f e1 02 55 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 cf 00 70 00 00 01', $block);
                    break;
                case 52:
                    $this->assertEquals('78 da ed c1 01 01 00 00 00 80 90 fe af ee 08 0a 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 18 80 00 00 01', $block);
                    break;
                case 54:
                    $this->assertEquals('78 da ed c3 31 0d 00 00 08 03 b0 05 ff a2 f1 b0 07 8e 36 69 02 00 00 00 00 00 00 00 00 00 c0 a5 29 02 00 00 00 00 00 00 00 00 00 00 00 00 00 f0 c7 02 4a ca 00 1b', $block);
                    break;
                default:
                    //var_dump($entrysize, $block);
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

        $waitingNext = 'ff ff ff ff 03 00 00 00';
        if ($this->file->hexahead(8) != $waitingNext) {
            // огромный кусок непонятно чего
            // похоже, статичен по длине для пересохранённых файлов оригинальной кампании
            $this->file->skip(1923774);
        }
        $this->nextEqualHex($waitingNext);

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

        $this->nextEqualHex($camBlock);

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
            $obj = new mapobject;
            $obj->type = $this->int32();
            $obj->mapuid = $this->int32();
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
            $this->mis->addObject($obj->mapuid, $object);
        }
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

        $type = $this->unknownBlock(4); // подозрительная штучка, может тут и можно определить, машинка дальше и чулавечек?
        if ($this->file->hexahead(4) != 'ff ff ff ff') {
            //$this->assertEquals('00 00 00 00', $obj->unknown0);
            // всякий хлам на земле
            // пока грязный хак, потом помержить с нормальными уже известными структурами
            // здесь явно прослеживается стандартная структура объектов
            $ammo = $obj->reinitAsAmmunition();
            $ammo->structId = $this->int32();
            $ammo->equipId = $this->int32();
            $this->int32();
            $this->nextEqualHex('00');
            $ammo->unknownAmmo0 = $this->unknownBlock(4);
            $this->nextEqualHex('01 00 00 00 00 00', '01 14 00 00 00 00');
            switch ($ammo->type) {
                case 2001:
                case 2000:
                    // ручное оружие (ак74)
                    $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 f4 01 00 00');
                    $this->unknownBlock(24);
                    $this->nextEqualHex('00 00 00 00 00 00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff 0c 75 6e 6b 6e 6f 77 6e 20 6e 61 6d 65 ff ff ff ff 00 00 00 00 00');
                    break;
                case 2037:
                    // ползучий мин
                    $this->unknownBlock(4);
                    $this->nextEqualHex('00 ff ff ff ff 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
                    break;
                case 2033:
                    // какой-то ящик
                    $this->nextEqualHex('00 00 ed 3e');
                    $inBoxCount = $this->int32();
                    for ($structCounter = 0; $structCounter < $inBoxCount; ++$structCounter) {
                        $this->ammonitionParser();
                    }
                    break;
                case 2007:
                    // чумадан
                    break;
                default:
                    throw new ParserError('unknown struct for '.$ammo->type . ' '.$ammo->structId);
            }
            $this->nextEqualHex('00 00 00 00');
            return $ammo;
        }
        $this->nextEqualHex('ff ff ff ff');
        // хуманы и мафынки

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
            $obj->addAmmunitionItem($this->ammonitionParser());
        }

        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00');

        $obj->unknownActive1 = $this->unknownblock(2);
        $this->nextEqualHex('ff 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
        $obj->unknownActive2 = $this->unknownblock(1);
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00');

        $nextStructType = $this->nextEqualHex(
            '01 00 00 00', //животные?
            '1e 00 00 00', // машинки
            '0a 00 00 00' // людишки
        );

        switch ($nextStructType) {
            case '01 00 00 00':
                $this->nextEqualHex('01 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00');
                if (! in_array($obj->type, [4009, 4010])) {
                    // не птычки
                    $this->nextEqualHex('00 00 00 00 00');
                }
                $this->assertEquals('00 00 00 00', $obj->unknown0);
                return $obj->reinitAsAnimal();
            case '1e 00 00 00':
                $unit = $obj->reinitAsVehicle();
                break;
            case '0a 00 00 00':
                $unit = $obj->reinitAsHuman();
                break;
        }

        $ammoSlotsCount = $this->int32();
        for ($slot = 0; $slot < $ammoSlotsCount; ++$slot) {
            $unit->ammunitionSlots[] = [];
            $entrysCount = $this->int32();
            for ($entry = 0; $entry < $entrysCount; ++$entry) {
                $unit->ammunitionSlots[ $slot ][] = $this->int32();
            }
        }

        $this->nextEqualHex('02 00 00 00');

        if ($unit instanceof vehicle) {
            $this->mapObjectVehicle($unit);
        } elseif ($unit instanceof human) {
            $this->mapObjectHuman($unit);
        } else {
            throw new \LogicException('wrong object class');
        }
        return $unit;
    }

    protected function ammonitionParser(array $validOnlyType = null, mapobject $context = null)
    {
        $ammoStructType = $this->int32();

        if ($ammoStructType == 0) {
            return null;
        }

        if (is_array($validOnlyType) and !in_array($ammoStructType, $validOnlyType)) {
            throw new \LogicException('ammo struct '.$ammoStructType.' not valid here');
        }

        $ammoObjectId = $this->int32(); // тип объекта
        $ammoRelationObjectId = $this->int32(); // id, на который потом ссылаются в описании багажника

        switch ($ammoStructType) {
            case 1:
                // всякая хрень вроде чумодана
                $obj = new other;
                $this->nextEqualHex('00 ff ff ff ff 01');
                break;
            case 4:
                $obj = new ammonition;
                $this->nextEqualHex('00 0e 00 0d 00 01');
                $obj->count = $this->int32(); // число боеприпасов, т.е. например номинальные 250 для 14,5мм ленты
                $this->nextEqualHex('00 01');
                break;
            case 8:
                $this->nextEqualHex('00 0e 00 0d 00 01');
                $obj = new armor;
                $obj->armor = $this->int32();
                if (! in_array($obj->armor, [
                    40, // лёгкий броник
                    80, // тяжёлый
                ])) {
                    throw new LogicException('unknown armor size '.$armor);
                }
                break;
            case 2:  // ручное оружие, ак-74, рпк, узи и др
                $obj = $this->ammonitionParserWeapon(new weapon, $context);
                break;
            case 34: // гранаты, молотов
                $obj = $this->ammonitionParserWeapon(new weaponGranate, $context);
                break;
            case 66: // sa7, рпг, м79
                $obj = $this->ammonitionParserWeapon(new weaponGranateLauncher, $context);
                break;
            default:
                throw new \LogicException('unknown '.$ammoStructType);
        }

        $obj->relationUid = $ammoRelationObjectId;
        $obj->type = $ammoObjectId;
        return $obj;
    }

    protected function ammonitionParserWeapon($obj, mapobject $context = null)
    {
        $this->nextEqualHex('00');
        $this->unknownblock(4);

        $this->nextEqualHex('01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 f4 01 00 00');

        if ($context and get_class($context) == activeobject::class) {
            $obj->unknownblock = $this->unknownblock(24);
            $obj->ammo = $this->ammonitionParser([ 4 ]);
        } elseif ($obj instanceof weaponGranateLauncher and ! $context) {
            $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $obj->unknownblock = $this->unknownblock(4);
            $this->nextEqualHex('00 00 00 01 00 00 00 00');
        } else {
            $this->nextEqualHex('40 00 41 00 42 00 41 00 47 00 46 00 00 00 00 00 4a 00 49 00 00 00 4a');
            $this->nextEqualHex('00', '01');
            $obj->ammo = $this->ammonitionParser([ 4 ]); // интересно что ручные гранаты парсятся тоже так
        }

        $this->nextEqualHex('00 00 00 00 00');
        if ($context) {
            $this->assertEquals($context->mapuid, $this->int32()); // зачем-то повторяется аж сразу 2 раза
            $this->assertEquals($context->mapuid, $this->int32());
            if ($context instanceof human) {
                $this->nextEqualHex('01 00 00 00', '00 00 00 00');
            } else {
                $this->nextEqualHex(
                    'ff ff ff ff',
                    '02 00 00 00',
                    '04 00 00 00'
                );
            }

            $obj->text = $this->text();

            if ($context instanceof human) {
                $this->nextEqualHex('04 00 00 00');
                $this->assertEquals($context->humanname, $obj->text); // ??? реально продублировано имя человека в оружии
            } else {
                $this->nextEqualHex(
                    '0d 00 00 00',
                    '00 00 00 00'
                );
            }
        } else {
            $this->nextEqualHex('ff ff ff ff ff ff ff ff ff ff ff ff');
            $obj->text = $this->text();
            $this->nextEqualHex('ff ff ff ff');
        }

        return $obj;
    }

    protected function mapObjectVehicle(vehicle $obj)
    {
        // что-то не так со всеми 3 самолётами, после них ещё 5 байт нулей потеряшек
        // байк, 2c3, btr80 парсятся
        $this->nextEqualHex('00 00 00 00 80 bf 00 00 80 bf 00 00 00 00 00 00 00 00 00 00 00');
        $this->nextEqualHex('00 00 00 00');
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
        $this->nextEqualHex('00 00 00 00');

        $selectableWeaponsCount = $this->int8();
        for ($i = 0; $i < $selectableWeaponsCount; ++$i) {
            $obj->maybeSelectableWeapon[] = [
                $this->int32(), // наверное, id позиции оружия
                $this->int32(), // uid оружия?
            ];
        }

        $this->nextEqualHex('00 00 00 00 00 00 80 3f 00 00 00 00 00');
        $this->nextEqualHex('00');
        if (in_array($obj->type, [2, 10, 22])) {
            $this->nextEqualHex('00 00 00 00 00');
        }

        if ($obj->unknown0 === '00 00 00 00') {
            $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 f0 55 00 00 0a d7 23 3c 40 08 f4 34 00 00 00 00 01 00');
            $obj->unknownVehicle6 = $this->unknownblock(13);
            $this->nextEqualHex('00 00 80 3f f0 55 00 00 0a d7 23 3c 40 08 f4 34 00 00 00 00 00 00 00 00 00 00 40 42 0f 00 00 00 00 00 00 00 00 80 40 00 00 00 00');
        }
    }

    protected function mapObjectHuman(human $obj)
    {
        $this->assertEquals('00 00 00 00', $obj->unknown0);

        $obj->humanname = $this->text();;
        $inUnit = $this->int8();
        if ($inUnit == 0) {
            $this->nextEqualHex('ff ff ff ff ff ff ff ff');
        } elseif ($inUnit == 1) {
            $obj->inUnitUid = $this->int32();
            $obj->inUnitPosition = $this->int32();
        } else {
            throw new LogicException('unknown in unit '.$inUnit);
        }

        $this->nextEqualHex('04 00 00 00');
        $obj->humanUnknown0 = $this->unknownblock(36);
        $this->nextEqualHex('00 50 c3 c7 00 50 c3 c7');
        $this->assertEquals($inUnit, $this->int8());
        $this->nextEqualHex('00 00 00 00 00 02 00 00 00 00 00 80 bf 00 00 80 bf 00 00 00 00');
        $obj->humanUnknown1 = $this->unknownblock(6);
        $this->nextEqualHex('00 00 00 00 00 00 00');
        $obj->humanUnknown2 = $this->unknownblock(2);
        $this->nextEqualHex('00 00 01 00 00 00 00 00 00 00 00 00 00 00');
        // маркер принадлежности к команде должен быть до этого момента, дальше свою-чужой бинарно идинтичны

        // броник
        $obj->inventoryArmor = $this->ammonitionParser([8]);
        // бинокль
        $obj->inventoryBinokle = $this->ammonitionParser([1]);
        // оружие в руках
        $obj->inventoryWeapon = $this->ammonitionParser([2, 34, 66], $obj);

        $this->nextEqualHex('ff ff ff ff 03 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
        $this->level = $this->int32();
        $this->experience = $this->int32(); // удвоенное число необходимых убийств для этого уровня. Возможно, убитый человек +2, животное +1
        $this->nextEqualHex('00 00 00 00');
        $obj->humanUnknown3 = $this->unknownblock(4+1);
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 01 0b 00 00 00');
        $obj->humanUnknown4 = $this->unknownblock(1+4);
        $this->nextEqualHex('00 00 00 00 00 00 00');
        $obj->humanUnknown5 = $this->unknownblock(8); // реакция на ПНВ, бинокль
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
        // навыки
        $obj->skill1 = $this->int32();
        $obj->skill2 = $this->int32();
        $obj->humanUnknown6 = $this->unknownblock(2);
        $this->nextEqualHex('00 00 00 00 00 00 00 00 00 00 00 00');
        $gender = $this->int8();
        switch ($gender) {
            case 0: // 0 - м, 1 - ж
                $obj->gender = 'male';
                break;
            case 1:
                $obj->gender = 'female';
                break;
            default:
                throw new LogicException('gender ' . $gender . ' unknown');
        }

        $this->nextEqualHex('00 00 00 00');
        $this->humanUnknown7 = $this->unknownblock(1);
        $this->nextEqualHex('0b 00 00');
        $marker = $this->int8();
        if ($marker == 1) {
            if (! $obj->isKnight()) {
                throw new \LogicException('strange marker not knight '.$marker);
            }
            // рыцарь
            $this->nextEqualHex('00 00 80 3e 00 00 00 00 00 ff ff ff ff 00 00');
        } elseif ($marker != 0) {
            throw new LogicException('strange marker '.$marker);
        }
        $this->nextEqualHex('00 00 00');
    }

    protected function objectLandscapeMapVersionSpecific() {}

    protected function objectHeaderConst()
    {
        $this->nextEqualHex('01', '00');
        $this->nextEqualHex('01 00 00 00 00 16 00 00 00 00 00 00 00');
    }

    protected function objectHeaderBlock2(mapobject $obj)
    {
        $this->nextEqualHex('00 00 00 00', '01 00 00 00');
        $obj->unknown2 = $this->unknownBlock(8);
        $this->nextEqualHex('00 00 00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff');
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