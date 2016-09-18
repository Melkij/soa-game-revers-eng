<?php

function assertEquals($equal, $value)
{
    if ($value !== $equal) {
        throw new LogicException('value ' . $value. ' not match need ' . $equal);
    }
}

class binaryFile
{
    private $file = '';
    private $position = 0;
    private $fileSize = 0;
    public function __construct($filename)
    {
        $this->file = file_get_contents($filename);
        $this->fileSize = mb_strlen($this->file, 'binary');
    }

    private function binhex($input, $sep = ' ') {
        $hex = '';
        foreach (str_split($input,1) as $w) {
            $hex .= bin2hex($w).$sep;
        }
        return rtrim($hex);
    }

    public function readaheadbyte($len)
    {
        return mb_substr($this->file, $this->position, $len, 'binary');
    }

    public function hexahead($len)
    {
        return $this->binhex($this->readaheadbyte($len));
    }

    private function readbyte($len)
    {
        $bytes = $this->readaheadbyte($len);
        $this->position += $len;
        return $bytes;
    }

    /**
     * testcase: 99 99 19 3f ~ 0,6
     */
    public function float()
    {
        return current(unpack('f', $this->readbyte(4)));
    }
    public function int32()
    {
        return current(unpack('V', $this->readbyte(4)));
    }
    public function int8()
    {
        return ord($this->readbyte(1));
    }

    public function utf8Text($len)
    {
        return iconv('utf8', 'utf8', $this->readbyte($len)); // utf to utf iconv to verify utf binary
    }

    public function unknownblock($len)
    {
        echo 'notice: unknown block ' . $this->position .' '. $len .' bytes',PHP_EOL;
        //echo $this->hexahead($len).PHP_EOL.PHP_EOL;
        $this->position += $len;
    }

    public function assertEqualHex($equals)
    {
        $len = preg_match_all('~[\da-f]{2}~', $equals, $null);
        $infile = $this->binhex($this->readbyte($len));
        if ($equals !== $infile) {
            echo $infile,PHP_EOL;
            echo $equals,PHP_EOL;
            echo PHP_EOL;
            throw new LogicException('readed block ' . $infile. ' not match need ' . $equals);
        }
    }

    public function assertEof()
    {
        if ($this->position !== $this->fileSize) {
            throw new \LogicException('not EOF!');
        }
    }
}

$file = new binaryFile('testmis/empty_5x5_unit2c3.mis');
$file = new binaryFile('testmis/buildx1.mis');
//$file = new binaryFile('testmis/f15x1.mis');
//$file = new binaryFile('testmis/empty_5x5_unit2c3x3_armored.mis');
$file = new binaryFile('testmis/testeditorcam_base.mis');
$file = new binaryFile('testmis/testeditorcam_position_up.mis');
$file->assertEqualHex('38 f9 b3 0a 62 93 d1 11 9a 2b 08 00 00 30 05 12 0a 00 00 00 02 00 00 00 0a 00 00 00');
$titleLenght = $file->int8();
$title = $file->utf8Text($titleLenght);
$file->assertEqualHex('00');
$descrLenght = $file->int8(); // описание максимум 255 байт?
$descr = $file->utf8Text($descrLenght);

$mapwidth = $file->int32();
$mapheight = $file->int32();

/* в этом блоке очень много нулей, возможно неверно определены повторяющиеся области, например int32 а не int8
для 1, 2, 3 и 4 команд соответственно:
01  00 00 00 00 01                                                 00 00 00 00 00 00 01    00 00 00 00 00 00 00 00                                                                             00 00 00 00
02  00 00 00 00 01 00 00 00 00 02                                  00 00 00 00 00 00 02    00 00 00 00 00 00 00 00  00 00 00 00 00 00 00 01                                                    00 00 00 00
03  00 00 00 00 01 00 00 00 00 02 00 00 00 00 02                   00 00 00 00 00 00 03    00 00 00 00 00 00 00 00  00 00 00 00 00 00 00 01  00 00 00 00 00 00 00 02                           00 00 00 00
04  00 00 00 00 01 00 00 00 00 02 00 00 00 00 02 00 00 00 00 02    00 00 00 00 00 00 04    00 00 00 00 00 00 00 00  00 00 00 00 00 00 00 01  00 00 00 00 00 00 00 02  00 00 00 00 00 00 00 03  00 00 00 00
*/
$partyCount = $file->int8();
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $file->assertEqualHex('00 00 00 00');
    $partyType = $file->int8(); // 1 is human, 2 is computer?
}
$file->assertEqualHex('00 00 00 00 00 00');
assertEquals($partyCount, $file->int8());
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $file->assertEqualHex('00 00 00 00 00 00 00');
    assertEquals($partyId, $file->int8());
}
$file->assertEqualHex('00 00 00 00');

$updateTimeStamp = $file->int32();
echo date('Y-m-d H:i:s', $updateTimeStamp).PHP_EOL;

$authorLenght = $file->int8();
$descr = $file->utf8Text($authorLenght);

$file->assertEqualHex('00 00 00 00 09 00 00 00');
assertEquals($mapwidth, $file->int32());
assertEquals($mapheight, $file->int32());

// карта высот
while($entrysize = $file->int32()) {
    $file->unknownblock($entrysize);
}
while($entrysize = $file->int32()) {
    $file->unknownblock($entrysize);
}
$file->assertEqualHex('02 00 00 00 00 00 00 00');
$landId = $file->int32();
$file->assertEqualHex('ff ff ff ff 03 00 00 00');
$editorCam = $file->hexahead(24);
// где находится камера
$editorCamPosWestEast = $file->float(); // при движении на запад уменьшается, скорей всего 0 - крайняя западная точка
$editorCamPosNorthSouth = $file->float(); // 0 в крайнем севере
$editorCamPosHeight = $file->float(); // 0 уровень моря, чем больше 0 - тем выше
// куда камера развёрнута
$editorCamViewDirectionX = $file->float(); // явно координаты взгляда, мог напутать x и y
$editorCamViewDirectionY = $file->float();
$editorCamViewDirectionZ = $file->float(); // но это точно z
$file->unknownblock(8); // неизвестный промежуточный блок
$file->assertEqualHex($editorCam); // затем блок данных о камере продублирован
$file->assertEqualHex('00 00 00 00 00 00 00 00');
$minimapsize = $file->float(); // множитель масштаба. Дефолт 00 00 80 3F (т.е. 1), число меньше - карта ближе, больше - дальше
$file->assertEqualHex('05 00 00 00');
$skies = $file->int32();
$file->assertEqualHex('00 00 00 00');
$rainPercent = $file->float();
$temperature = $file->float();
$file->assertEqualHex('00 00 00 00 00 0c 00 00');
$file->assertEqualHex('00');
$file->unknownblock(4); // скорей всего здесь игровое время +- возможное смещение на 1 байт. 3 байта точно меняются
$file->assertEqualHex('02 00 00 00');

assertEquals($partyCount, $file->int8());
$file->assertEqualHex('00 00 00');
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $partyType = $file->int8();
    $file->assertEqualHex('00 00 00 0c 00 00 00');
    assertEquals($partyId, $file->int8());
    $file->assertEqualHex('00 00 00 00 00 80 bf 00 00 80 bf');
    $playerNameLen = $file->int8();
    $playerName = $file->utf8Text($playerNameLen);
    $file->assertEqualHex('00 00 00 00 00');
    $file->unknownblock(28);
    $file->assertEqualHex('00 00 00 00 01 01 00 00 00 00 00 00 00 00');
}

$file->assertEqualHex('63 00 00 00');

// физические объекты на карте, в том числе транспорт
$objectsCount = $file->int32();
//echo $file->hexahead(2000000),PHP_EOL;
$unitInnerStructId = 0;
for ($objectStructCounter = 0; $objectStructCounter < $objectsCount; ++$objectStructCounter) {
    $objectTypeId = $file->int32();
    $objectUid = $file->int32(4); // какое-то числительное int32
    $file->unknownblock(8); // may be, position?
    $file->unknownblock(4); // 00 00 00 80 для юнитов, 00 00 00 00 для сооружений?
    $file->unknownblock(4); // угол поворота?
    $file->assertEqualHex('00 00 80 3f 01 01 00 00 00 00 16 00 00 00 00 00 00 00 00');
    $file->unknownblock(4); // тоже поменялось с углом поворота
    assertEquals($file->int32(), $file->int32()); // две непонятные пары повторяющихся совершенно идентичных 4 байт,
    assertEquals($file->int32(), $file->int32()); // идентичны для нескольких одинаковый объектов, различны для разных объектов
    $file->assertEqualHex('ff ff ff ff 00 00 00 00 00 00 00 00 00 00 80 3f 00 00 00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff');
    $nameLen = $file->int8();
    $name = $file->utf8Text($nameLen);
    $file->assertEqualHex('ff ff ff ff 00 00 00 00 00 00 00 00');
    switch ($file->hexahead(8)) {
        case '00 00 00 00 00 00 00 00':
            // постройка?
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            break;
        case 'ff ff ff ff ff ff ff ff':
            // юнит?
            $file->assertEqualHex('ff ff ff ff ff ff ff ff 00 00 00 00 00 00 00 00 00 01 40 42 0f 00 00 00 00 00');
            $targetStructCount = $file->int32();
            $repeatableUnknownBlock1 = null;
            for ($structCounter = 0; $structCounter < $targetStructCount; ++$structCounter) {
                if (is_null($repeatableUnknownBlock1)) {
                    $repeatableUnknownBlock1 = $file->int32();
                } else {
                    assertEquals($repeatableUnknownBlock1, $file->int32());
                }
                $file->unknownblock(4); // различается иногда
                assertEquals($unitInnerStructId, $file->int32());
                $file->assertEqualHex('00');
                $file->unknownblock(4); // различается иногда
                $file->assertEqualHex('01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 f4 01 00 00');
                $file->unknownblock(12);
                $file->assertEqualHex('00 00 00 00');
                $file->unknownblock(8);
                $file->assertEqualHex('00 00 00 00 00 00 00 00 00');
                assertEquals($objectUid, $file->int32()); // зачем-то повторяется аж сразу 2 раза
                assertEquals($objectUid, $file->int32());
                $file->assertEqualHex('ff ff ff ff');
                $objectName = $file->utf8Text($file->int8()); // TRES_OBJECTS_UNIT_*
                $file->unknownblock(4);

                ++$unitInnerStructId;
            }
            // todo: посмотреть в другие юниты. f15 и 2c3 различаются не только данными, но и длиной записи
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 ff 00 ff 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $file->unknownblock(1);
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 1e 00 00 00');
            $unknownLength = $file->int32();
            for ($i = 0; $i < $unknownLength; ++$i) {
                $file->assertEqualHex('00 00 00 00');
            }
            $file->assertEqualHex('02 00 00 00 00 00 00 00 80 bf 00 00 80 bf 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $file->unknownblock(1);
            $file->assertEqualHex('00 00 80 bf 00 00 80 bf');
            $file->unknownblock(24);
            $file->assertEqualHex('00 00 00 00 00');
            $file->unknownblock(2);
            $file->assertEqualHex('00 00 00');
            $file->unknownblock(20);
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 80 3f 00 00 00 00 00 00');
            break;
        default:
            throw new \LogicException('unknown bytea '.$file->hexahead(8));
    }
}

$ending = str_replace(PHP_EOL, ' ', '01 00 00 00 00 00 00 00
02 00 00 00 00 00 00 00 00 00 00 00
24 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 07 00 00 00 00 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
00 00 00 00
00 00 00 00');
$file->assertEqualHex($ending);
$file->assertEof();

echo 'read complete',PHP_EOL;
