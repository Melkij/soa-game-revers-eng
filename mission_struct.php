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
        $this->filesize = mb_strlen($this->file, 'binary');
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
}

$file = new binaryFile('testmis/empty_5x5.mis');
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
$unknownmarker = $file->hexahead(24);
$file->unknownblock(24);
$file->unknownblock(8);
//$file->assertEqualHex('00 00 00 00 00 00 00 00 34 cd 9f 42 f4 04 35 3f f4 04 35 3f fe 04 35 bf');
//$file->assertEqualHex('4e 61 3c 4b 00 00 a0 41');
$file->assertEqualHex($unknownmarker); // маркер повторяется
$file->assertEqualHex('00 00 00 00 00 00 00 00');
$file->unknownblock(4);
$file->assertEqualHex('05 00 00 00');
$skies = $file->int32();
$file->assertEqualHex('00 00 00 00');
$file->unknownblock(4); // possible, rain
$file->assertEqualHex('00 00');
$file->unknownblock(1); // possible, temp
$file->assertEqualHex('41 00 00 00 00 00 0c 00 00 00');
$file->unknownblock(4); // possible, time
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
for ($objectId = 0; $objectId < $objectsCount; ++$objectId) {
    throw new LogicException('unit undefined binary length for each');
}

$ending = str_replace(PHP_EOL, ' ', '01 00 00 00 00 00 00 00
02 00 00 00 00 00 00 00 00 00 00 00
24 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
64 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 07 00 00 00 00 00 00 00 00 00 00 00 02 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 01 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00
00 00 00 00
00 00 00 00');
$file->assertEqualHex($ending);

echo 'read complete',PHP_EOL;
