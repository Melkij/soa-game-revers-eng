<?php

namespace struct;

class Trs
{
    public static function readFromFile($filename)
    {
        $trs = new static;
        misparser\trsparser::load(BinaryFile::readFile($filename), $trs);
        return $trs;
    }

    private $records = [];
    public function addRecord($id, array $data)
    {
        if (isset($this->records[ $id ])) {
            throw new \LogicException('dup record '.$id);
        }
        $this->records[ $id ] = $data;
    }

    public function getRecords()
    {
        return $this->records;
    }
}
