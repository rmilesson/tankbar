<?php

spl_autoload_register(
    function ($className) {
        $vendor = 'Tankbar';
        if (strpos($className, $vendor) === 0) {
            $className = substr($className, strlen($vendor), strlen($className));
        }

        $path = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        $path = __DIR__ . "/$path.php";
        if (file_exists($path)) {
            require_once $path;
        }
    },
    true,
    true
);
