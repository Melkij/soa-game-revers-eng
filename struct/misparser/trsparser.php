<?php

namespace struct\misparser;

use struct\BinaryFile;
use struct\Trs;
use struct\misparser\ParserError;

class trsparser
{
    public static function load(BinaryFile $file, Trs $text)
    {
        return (new static)->process($file, $text);
    }

    protected $file;
    protected function process(BinaryFile $file, Trs $text)
    {
        $this->file = $file;
        $this->assertEquals(0, $this->file->getPosition());
        $this->nextEqualHex('40 f9 b3 0a 62 93 d1 11 9a 2b 08 00 00 30 05 12');
        $fileversion = $this->int32();
        $rowsCount = $this->int32();
        for ($i = 0; $i < $rowsCount; $i++) {
            $id = $this->int32();
            $data = [
                'ident' => $this->text(),
                'audiofile' => $this->text() ?: null,
                'text' => $this->text(),
                'localComment' => $this->text(),
            ];
            if ($fileversion >= 4) {
                $data['speaker'] = $this->text();
                $this->nextEqualHex('00 00 00 00');
            }
            $text->addRecord($id, $data);
        }
        if (! $this->file->isEof()) {
            throw new ParserError('not eof!');
        }
    }

    protected function int16()
    {
        $l = $this->int8();
        $h = $this->int8();
        return ($h << 8) + $l;
    }
    protected function int8()
    {
        return $this->file->int8();
    }
    protected function int32()
    {
        return $this->file->int32();
    }
    protected function text()
    {
        $len = $this->file->int8();
        if ($len === 255) {
            $len = $this->int16();
        }

        return $this->file->utf8Text($len);
    }

    protected function nextEqualHex(...$equals)
    {
        $len = null;
        $infile = '';
        foreach ($equals as $eq) {
            $eqlen = preg_match_all('~[\da-f]{2}~', $eq, $null);
            if (is_null($len)) {
                $len = $eqlen;
                $infile = $this->file->hexread($len);
            } elseif ($len !== $eqlen) {
                throw new ParserError('blocklen mismatch');
            }

            if ($eq === $infile) {
                return $infile;
            }
        }

        throw new ParserError('readed block ' . $infile. ' not match needed');
    }

    protected function assertEquals($equal, $value)
    {
        if ($value !== $equal) {
            throw new ParserError('value ' . $value. ' not match need ' . $equal);
        }
    }
}
