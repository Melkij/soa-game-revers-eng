<?php

spl_autoload_register(function($class) {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
    if (file_exists($file)) {
        include $file;
    }
});

set_error_handler(function ($errno, $errstr) {
    throw new \LogicException($errstr, $errno);
});
