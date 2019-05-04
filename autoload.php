<?php

spl_autoload_register(
    function ($className) {
        if (strpos($className, 'Tankbar') === 0) {
            $className = substr($className, 5, strlen($className));
        }

        $path = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        $path = __DIR__ . "/src/$path.php";
        if (file_exists($path)) {
            require_once $path;
        }
    },
    true,
    true
);
