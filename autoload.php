<?php

spl_autoload_register(
    function ($className) {
        if (strpos($className, 'TBDB') === 0) {
            $className = substr($className, 5, strlen($className));
        }

        $path = str_replace('\\', '/', $className);
        $path = __DIR__ . "/src/$path.php";
        if (file_exists($path)) {
            require_once $path;
        }
    },
    true,
    true
);
