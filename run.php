<?php

use struct\Mission;
use struct\misparser\ParserError;

spl_autoload_register(function($class) {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
    if (file_exists($file)) {
        include $file;
    }
});

//var_dump(Mission::readFromFile('/home/melkij/tmp/soa/missions/Mission_usa.mis'));

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
echo $count . ' total, '. ($count - count($failed) - count($notimplement)) .' success, ', count($notimplement), ' not implement, ', count($failed) . ' failed.',PHP_EOL;
if ($failed) {
    echo 'failed files:',PHP_EOL;
    echo join(PHP_EOL, $failed);
}
echo PHP_EOL;
