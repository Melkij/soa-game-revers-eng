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

    public function getPosition()
    {
        return $this->position;
    }

    public function getMaxPosition()
    {
        return $this->fileSize;
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
        //echo 'notice: unknown block ' . $this->position .' '. $len .' bytes',PHP_EOL;
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
$file = new binaryFile('testmis/building_baraks1-7.mis');
//$file = new binaryFile('testmis/btr80x3_arm_gasgranate.mis');
//$file = new binaryFile('testmis/empty_5x5_unit2c3x3_armored.mis');
//$file = new binaryFile('testmis/empty_5x5_unit2c3x3_armored.mis');
$file = new binaryFile('testmis/scriptx3_mission_start.mis');
$file->assertEqualHex('38 f9 b3 0a 62 93 d1 11 9a 2b 08 00 00 30 05 12 0a 00 00 00 02 00 00 00 0a 00 00 00');
$titleLenght = $file->int8();
$title = $file->utf8Text($titleLenght);
$file->assertEqualHex('00');
$descrLenght = $file->int8(); // описание максимум 255 байт?
$descr = $file->utf8Text($descrLenght);

$mapwidth = $file->int32();
$mapheight = $file->int32();
//echo $file->hexahead($file->getMaxPosition() - $file->getPosition() - 172),PHP_EOL;
/* для 3, 4, 5 (один человек, отстальние ии) и 5 (3 человека, 2 компа) команд соответственно:
00 00 00 00     01 00 00 00   00        02 00 00 00 00      02 00 00 00 00                                              00 00
4pl
00 00 00 00     01 00 00 00   00        02 00 00 00 00      02 00 00 00 00      02 00 00 00 00                          00 00
5pl
00 00 00 00     01 00 00 00   00        02 00 00 00 00      02 00 00 00 00      02 00 00 00 00      02 00 00 00 00      00 00
5pl_3h
00 00 00 00     01 00 00 00   01 01     01 00 00 00 01 02   01 00 00 00 01 03   02 00 00 00 01 04   02 00 00 00 01 05   00 00
*/
$partyCount = $file->int32();
$file->assertEqualHex('00');
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $partyType = $file->int32(); // 1 is human, 2 is computer?
    if ($partyType == 1) {
        // human
    } elseif ($partyType == 2) {
        // ai
    } else {
        throw new LogicException('unknown partytype '.$partyType);
    }
    $readAdd = $file->int8();
    if ($readAdd) {
        // возможно, $partyId + 1, но не хватает разрядности.
        // Хм, а может игроков-людей максимум 255? Этот блок в 1 байт появляется только при добавлении кторого игрока-человека. Может, id для сетевой игры?
        $file->unknownblock($readAdd);
    }
}
$file->assertEqualHex('00 00');
assertEquals($partyCount, $file->int32());
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $file->assertEqualHex('00 00 00 00');
    assertEquals($partyId, $file->int32());
}
$file->assertEqualHex('00');

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
$file->assertEqualHex('02 00 00 00');
$texturesCount = $file->int32();
for ($i = 0; $i < $texturesCount; ++$i) {
    $pos1 = $file->float();
    $pos2 = $file->float();
    $size1 = $file->float();
    $size2 = $file->float();
    $file->assertEqualHex('00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff ff ff ff ff 2d 00 00 00 00 00 00 00');
}
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
$minimapsize = $file->float(); // множитель масштаба миникарты. Дефолт 00 00 80 3F (т.е. 1), число меньше - карта ближе, больше - дальше
$file->assertEqualHex('05 00 00 00');
$skies = $file->int32();
$file->assertEqualHex('00 00 00 00');
$rainPercent = $file->float();
$temperature = $file->float();
$file->assertEqualHex('00 00 00 00 00 0c 00 00');
$file->assertEqualHex('00');
$file->unknownblock(4); // скорей всего здесь игровое время +- возможное смещение на 1 байт. 3 байта точно меняются
$file->assertEqualHex('02 00 00 00');

assertEquals($partyCount, $file->int32());
/**
найти куски релевантные и поиграться с ними
3pl
09 00 00 00 05 00 00 00 05 00 00 00 23 00 00 00 78 da ed c1 01 0d 00 00 00 c2 a0 f7 4f 6d 0f 07 14 00 00 00 00 00 00 00 00 00 00 00 00 3c 1b 33 42 00 01 00 00 00 00 30 00 00 00 78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 ff ff ff ff 03 00 00 00 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 4e 61 3c 4b 00 00 a0 41 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 00 00 00 00 00 00 00 00 00 00 80 3f 05 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 a0 41 00 00 00 00 00 0c 00 00 00 00 00 00 00 02 00 00 00 03 00 00 00 01 00 00 00 0c 00 00 00 00 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 74 01 e4 03 40 a6 e4 03 d8 d4 e4 03 b0 0f e5 03 b8 14 e5 03 80 14 e5 03 58 a4 84 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 01 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 79 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 02 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 7e 01 01 00 02 00 01 00 03 00 02 00 04 00 05 00 06 00 05 00 07 00 06 00 3d 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 63 00 00 00 00 00 00 00
4pl
09 00 00 00 05 00 00 00 05 00 00 00 23 00 00 00 78 da ed c1 01 0d 00 00 00 c2 a0 f7 4f 6d 0f 07 14 00 00 00 00 00 00 00 00 00 00 00 00 3c 1b 33 42 00 01 00 00 00 00 30 00 00 00 78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 ff ff ff ff 03 00 00 00 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 4e 61 3c 4b 00 00 a0 41 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 00 00 00 00 00 00 00 00 00 00 80 3f 05 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 a0 41 00 00 00 00 00 0c 00 00 00 00 00 00 00 02 00 00 00 04 00 00 00 01 00 00 00 0c 00 00 00 00 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 74 01 e4 03 40 a6 e4 03 d8 d4 e4 03 b0 0f e5 03 b8 14 e5 03 80 14 e5 03 58 a4 84 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 01 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 79 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 02 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 7e 01 01 00 02 00 01 00 03 00 02 00 04 00 05 00 06 00 05 00 07 00 06 00 3d 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 03 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 97 01 ee 03 88 c6 ee 03 70 c7 ee 03 58 c8 ee 03 40 c9 ee 03 28 ca ee 03 00 c4 e4 03 00 00 00 00 01 01 00 00 00 00 00 00 00 00 63 00 00 00 00 00 00 00
5pl
09 00 00 00 05 00 00 00 05 00 00 00 23 00 00 00 78 da ed c1 01 0d 00 00 00 c2 a0 f7 4f 6d 0f 07 14 00 00 00 00 00 00 00 00 00 00 00 00 3c 1b 33 42 00 01 00 00 00 00 30 00 00 00 78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 ff ff ff ff 03 00 00 00 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 4e 61 3c 4b 00 00 a0 41 f1 09 26 40 50 ef 14 40 ac 39 63 42 f4 04 35 3f f4 04 35 3f 41 04 35 bf 00 00 00 00 00 00 00 00 00 00 80 3f 05 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 a0 41 00 00 00 00 00 0c 00 00 00 00 00 00 00 02 00 00 00 05 00 00 00 01 00 00 00 0c 00 00 00 00 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 74 01 e4 03 40 a6 e4 03 d8 d4 e4 03 b0 0f e5 03 b8 14 e5 03 80 14 e5 03 58 a4 84 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 01 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 79 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 02 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 7e 01 01 00 02 00 01 00 03 00 02 00 04 00 05 00 06 00 05 00 07 00 06 00 3d 00 00 00 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 03 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 97 01 ee 03 88 c6 ee 03 70 c7 ee 03 58 c8 ee 03 40 c9 ee 03 28 ca ee 03 00 c4 e4 03 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 04 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 ab 01 ee 03 88 c6 ee 03 70 c7 ee 03 58 c8 ee 03 40 c9 ee 03 28 ca ee 03 80 91 ea 03 00 00 00 00 01 01 00 00 00 00 00 00 00 00 63 00 00 00 00 00 00 00
5pl_3h
09 00 00 00 05 00 00 00 05 00 00 00 23 00 00 00 78 da ed c1 01 0d 00 00 00 c2 a0 f7 4f 6d 0f 07 14 00 00 00 00 00 00 00 00 00 00 00 00 3c 1b 33 42 00 01 00 00 00 00 30 00 00 00 78 da ed c1 31 01 00 00 00 c2 a0 f5 4f 6d 07 6f a0 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 d7 00 64 00 00 01 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 ff ff ff ff 03 00 00 00 6d 15 3b 41 6d 15 3b 41 da cc 2b 42 f4 04 35 3f f4 04 35 3f 34 04 35 bf 4e 61 3c 4b 00 00 a0 41 6d 15 3b 41 6d 15 3b 41 da cc 2b 42 f4 04 35 3f f4 04 35 3f 34 04 35 bf 00 00 00 00 00 00 00 00 00 00 80 3f 05 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 a0 41 00 00 00 00 00 0c 00 00 00 00 00 00 00 02 00 00 00 05 00 00 00 01 00 00 00 0c 00 00 00 00 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 74 01 e4 03 40 a6 e4 03 d8 d4 e4 03 b0 0f e5 03 b8 14 e5 03 80 14 e5 03 58 a4 84 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 01 00 00 00 0c 00 00 00 01 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 79 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 80 54 91 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 01 00 00 00 0c 00 00 00 02 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 7e 01 01 00 02 00 01 00 03 00 02 00 04 00 05 00 06 00 05 00 07 00 06 00 70 74 91 0c 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 03 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 97 01 ee 03 88 c6 ee 03 70 c7 ee 03 58 c8 ee 03 40 c9 ee 03 28 ca ee 03 00 c4 e4 03 00 00 00 00 01 01 00 00 00 00 00 00 00 00 02 00 00 00 0c 00 00 00 04 00 00 00 00 00 80 bf 00 00 80 bf 0a d0 98 d0 b3 d1 80 d0 be d0 ba 00 00 00 00 00 ab 01 ee 03 88 c6 ee 03 70 c7 ee 03 58 c8 ee 03 40 c9 ee 03 28 ca ee 03 80 91 ea 03 00 00 00 00 01 01 00 00 00 00 00 00 00 00 63 00 00 00 00 00 00 00
*/
for ($partyId = 0; $partyId < $partyCount; ++$partyId) {
    $partyType = $file->int32();
    $file->assertEqualHex('0c 00 00 00');
    assertEquals($partyId, $file->int32());
    $file->assertEqualHex('00 00 80 bf 00 00 80 bf');
    $playerNameLen = $file->int8();
    $playerName = $file->utf8Text($playerNameLen);
    $file->assertEqualHex('00 00 00 00 00');
    $file->unknownblock(28);
    $file->assertEqualHex('00 00 00 00 01 01 00 00 00 00 00 00 00 00');
}

$file->assertEqualHex('63 00 00 00');

$ammoDict = []; // словарь припасов в машинах
// физические объекты на карте, в том числе транспорт
$objectsCount = $file->int32();
//echo $file->hexahead($file->getMaxPosition() - $file->getPosition() - 172),PHP_EOL;
$unitInnerStructId = 0;
for ($objectStructCounter = 0; $objectStructCounter < $objectsCount; ++$objectStructCounter) {
    $objectTypeId = $file->int32();
    $objectUid = $file->int32(4); // какое-то числительное int32, по-видимому на него ссылаются дальше
    $position1 = $file->float();
    $position2 = $file->float();
    $maybeTypeBlock = $file->hexahead(4);
    $file->int32(); // 00 00 00 80 для юнитов, 00 00 00 00 для сооружений?
    $rotateAngle = $file->float(); // угол поворота?
    $file->assertEqualHex('00 00 80 3f 01 01 00 00 00 00 16 00 00 00 00 00 00 00');
    $file->unknownblock(1); // тоже поменялось с углом поворота
    $file->assertEqualHex('00 00 00 01');
    assertEquals($file->int32(), $file->int32()); // две непонятные пары повторяющихся совершенно идентичных 4 байт,
    assertEquals($file->int32(), $file->int32()); // идентичны для нескольких одинаковый объектов, различны для разных объектов
    $file->assertEqualHex('ff ff ff ff 00 00 00 00 00 00 00 00 00 00 80 3f 00 00 00 00 00 00 ff ff ff ff ff ff ff ff ff ff ff ff');
    $nameLen = $file->int8();
    $name = $file->utf8Text($nameLen);
    $file->assertEqualHex('ff ff ff ff 00 00 00 00 00 00 00 00');
    switch ($file->hexahead(8)) {
        case '00 00 00 00 00 00 00 00':
            echo 'Build type '.$objectTypeId.PHP_EOL;
            // постройка?
            assertEquals('00 00 00 00', $maybeTypeBlock);
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            break;
        case 'ff ff ff ff ff ff ff ff':
            // что-то не так с f15, после него ещё 5 байт нулей потеряшек
            // байк, 2c3, btr80 парсятся
echo 'Unit type '.$objectTypeId.PHP_EOL;
            assertEquals('00 00 00 80', $maybeTypeBlock);
            $file->assertEqualHex('ff ff ff ff ff ff ff ff 00 00 00 00 00 00 00 00 00 01 40 42 0f 00 00 00 00 00');
            $targetStructCount = $file->int32();
echo 'strange structs (weapons?) count: '.$targetStructCount.PHP_EOL;
            for ($structCounter = 0; $structCounter < $targetStructCount; ++$structCounter) {
                $file->unknownblock(4);
                $file->unknownblock(4);
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
            //echo $file->hexahead($file->getMaxPosition() - $file->getPosition() - 172),PHP_EOL,PHP_EOL;
            $ammoCount = $file->int32();
echo 'amm count '.$ammoCount.PHP_EOL;
            for ($ammo = 0; $ammo < $ammoCount; ++$ammo) {
                $file->assertEqualHex('04 00 00 00');
                $ammoObjectId = $file->int32();
                $ammoRelationObjectId = $file->int32();
                $file->assertEqualHex('00 0e 00 0d 00 01');
                $ammoSize = $file->int32(); // число боеприпасов, т.е. например номинальные 250 для 14,5мм ленты
                $file->assertEqualHex('00 01');
                if (isset($ammoDict[ $ammoRelationObjectId ])) {
                    throw new \LogicException('ammo dict ' . $ammoRelationObjectId . ' exists! Not global unique?');
                }
                $ammoDict[ $ammoRelationObjectId ] = [
                    'id' => $ammoObjectId,
                ];
            }
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 ff 00 ff 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
            $file->unknownblock(1);
            $file->assertEqualHex('00 00 00 00 00 00 00 00 00 1e 00 00 00');
            $ammoSlotsCount = $file->int32();
echo 'ammo slots count '.$ammoSlotsCount,PHP_EOL;
            for ($slot = 0; $slot < $ammoSlotsCount; ++$slot) {
                $entryCount = $file->int32();
                $objInSlotCount = 0;
                $objInSlotType = null;
                for ($entry = 0; $entry < $entryCount; ++$entry) {
                    $ammoEntryId = $file->int32();
                    if (is_null($objInSlotType)) {
                        $objInSlotType = $ammoDict[ $ammoEntryId ]['id'];
                    } elseif ($ammoDict[ $ammoEntryId ]['id'] !== $objInSlotType) {
                        throw new LogicException('one slot can containts only one item type');
                    }
                    ++$objInSlotCount;
                }
if (is_null($objInSlotType)) {
    echo 'ammo slot '.$slot . ' empty', PHP_EOL;
} else {
    echo 'ammo slot '.$slot . ' contants '.$objInSlotCount.' items type '.$objInSlotType, PHP_EOL;
}
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
            if ($objectTypeId == 22) {
                $file->assertEqualHex('00 00 00 00 00'); // see later
            }
            break;
        default:
            throw new \LogicException('unknown bytea '.$file->hexahead(8));
    }
}

$file->assertEqualHex('01 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 24 00 00 00');
$scriptsCount = $file->int32();
$strangeBlock = '00 00 00 00';
if ($scriptsCount) {
    $strangeBlock = '01 00 00 00';
    for ($i = 1; $i <= $scriptsCount; ++$i) {
        $file->assertEqualHex('d0 07 00 00');
        assertEquals($i, $file->int32());
    }
    for ($i = 0; $i < $scriptsCount; ++$i) {
        $file->assertEqualHex('00');
    }
    $blockCount = $file->int32();
    assertEquals($scriptsCount*2, $blockCount);
    for ($i = 0; $i < $blockCount; ++$i) {
        $file->unknownblock(9);
    }
    $file->assertEqualHex('00 00 00 00 01 00 00 00 01 00 00 00 01 00 00 00');
    $file->unknownblock(24 + 36 * ($scriptsCount-1));
    $nameBlockCount = $file->int32();
    for ($i = 0; $i < $nameBlockCount; ++$i) {
        $file->assertEqualHex('01 00 00 00');
        $file->unknownblock(4);
        $file->assertEqualHex('01 00 00 00 d0 07 00 00 01 00 00 00 00 00 00 00');
        $nameLen = $file->int8();
        $scriptName = $file->utf8Text($nameLen);
    }
    $unknownBlockLen = $file->int32();
    for ($i = 0; $i < $unknownBlockLen; ++$i) {
        $file->unknownblock(20);
    }
} else {
    $file->assertEqualHex('00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00');
}
$file->assertEqualHex('00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');
$file->assertEqualHex('64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 07 00 00 00');
$file->assertEqualHex($strangeBlock);
$file->assertEqualHex('00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00');

$file->assertEof();

echo 'read complete',PHP_EOL;
