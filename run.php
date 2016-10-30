<?php

use struct\Mission;
use struct\misparser\ParserError;

spl_autoload_register(function($class) {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
    if (file_exists($file)) {
        include $file;
    }
});

set_error_handler(function ($errno, $errstr) {
    throw new \LogicException($errstr, $errno);
});

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
