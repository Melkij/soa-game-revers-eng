<?php

namespace struct\misparser;

use struct\BinaryFile;
use struct\Mission;

abstract class base
{
    public static function load(BinaryFile $file, Mission $mis)
    {
        $parser = null;

        $file->reset();
        $file->skip(16);
        $versionMarker = $file->hexahead(12);
        switch ($versionMarker) {
            case normal::versionMarker():
                // игровой редактор оставляет такую метку
                $parser = new normal($file, $mis);
                break;
            case bunker::versionMarker():
                $parser = new bunker($file, $mis);
                break;
            case usamis::versionMarker():
                $parser = new usamis($file, $mis);
                break;
            default:
                throw new ParserError('unknown map binary version ' . $versionMarker);
        }

        $file->reset();

        $parser->process();

        return $parser;
    }

    protected $file;
    protected $mis;

    final protected function __construct(BinaryFile $file, Mission $mis)
    {
        $this->file = $file;
        $this->mis = $mis;
    }

    abstract protected function process();

    protected function int8()
    {
        return $this->file->int8();
    }
    protected function int32()
    {
        return $this->file->int32();
    }
    protected function float()
    {
        return $this->file->float();
    }

    protected function text()
    {
        return $this->file->utf8Text($this->file->int8());
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

    protected function unknownBlock($len)
    {
        return $this->file->hexread($len);
    }
}
