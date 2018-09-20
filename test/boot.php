<?php
/**
 * phpunit
 */

error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(function ($class) {
    $file = null;

    if (0 === strpos($class,'SwoKit\Memory\Example\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('SwoKit\Memory\Example\\')));
        $file = dirname(__DIR__) . "/example/{$path}.php";
    } elseif (0 === strpos($class,'SwoKit\Memory\Test\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('SwoKit\Memory\Test\\')));
        $file = __DIR__ . "/{$path}.php";
    } elseif (0 === strpos($class,'SwoKit\Memory\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('SwoKit\Memory\\')));
        $file = dirname(__DIR__) . "/src/{$path}.php";
    }

    if ($file && is_file($file)) {
        include $file;
    }
});
