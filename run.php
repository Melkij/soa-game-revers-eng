<?php

use struct\Mission;
use struct\Trs;
use struct\misparser\ParserError;

include 'struct/bootstrap.php';

$count = 0;
$failed = [];
$notimplement = [];
foreach (glob('testmis/*.mis') as $file) {
    ++$count;
    try {
        Mission::readFromFile($file);
    } catch (ParserError $e) {
        if ($e->getMessage() == 'not implement') {
            $notimplement[] = basename($file);
            continue;
        } else {
            $failed[] = basename($file);
            echo basename($file) . ' ParserError: ' . $e,PHP_EOL;
        }
    }
}
foreach (glob('trs/*.trs') as $file) {
    ++$count;
    try {
        Trs::readFromFile($file);
    } catch (ParserError $e) {
        $failed[] = basename($file);
        echo basename($file) . ' ParserError: ' . $e,PHP_EOL;
    }
}
echo $count . ' total, '. ($count - count($failed) - count($notimplement)) .' success, ', count($notimplement), ' not implement, ', count($failed) . ' failed.',PHP_EOL;
if ($failed) {
    echo 'failed files:',PHP_EOL;
    echo join(PHP_EOL, $failed);
}
echo PHP_EOL;
