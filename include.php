<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Автозагрузка классов модуля
spl_autoload_register(function ($className) {
    $prefix = 'Solka\\';
    $baseDir = __DIR__ . '/lib/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($className, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Подключаем основной класс
require_once __DIR__ . '/lib/TestUpdater.php';
?>