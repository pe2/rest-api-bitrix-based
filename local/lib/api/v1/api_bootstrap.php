<?php
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/lib/classes/init/service_handler.php';

// Autoloading classes
spl_autoload_register(function ($class) {
    $arClassFullName = explode('\\', $class);
    $className = array_pop($arClassFullName);
    $methodNameSpace = array_pop($arClassFullName);

    // Handlers classes
    $classNameFilePath = __DIR__ . '/handlers/' . str_replace('\\', '/', lcfirst($className) . '.php');
    if (file_exists($classNameFilePath)) {
        require_once $classNameFilePath;
    }

    // Tech classes
    $classNameFilePath = __DIR__ . '/methods/' . str_replace('\\', '/', lcfirst($className) . '.php');
    if (file_exists($classNameFilePath)) {
        require_once $classNameFilePath;
    }

    // Methods classes
    $classNameFilePath = __DIR__ . '/methods/' . lcfirst($methodNameSpace) . '/' . str_replace('\\', '/', lcfirst($className) . '.php');
    if (file_exists($classNameFilePath)) {
        require_once $classNameFilePath;
    }
});
