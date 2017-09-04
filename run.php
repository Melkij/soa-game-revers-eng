<?php

use struct\Mission;
use struct\Trs;
use struct\misparser\ParserError;
use struct\misparser\NotImplement;

include 'struct/bootstrap.php';

$count = 0;
$failed = [];
$notimplement = [];
foreach (glob('testmis/*.mis') as $file) {
    ++$count;
    try {
        Mission::readFromFile($file);
    } catch (NotImplement $e) {
        $notimplement[] = basename($file);
        continue;
    } catch (\Exception $e) {
        $failed[] = basename($file);
        echo basename($file) . ' ParserError: ' . $e,PHP_EOL;
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
    echo join(PHP_EOL, $failed),PHP_EOL;
}
if ($notimplement) {
    echo 'not implemented files:',PHP_EOL;
    echo join(PHP_EOL, $notimplement),PHP_EOL;
}
