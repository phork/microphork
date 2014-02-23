<?php
    //everything below can be overridden by an environmental file (this can also be auto-prepended by the server)
    (file_exists(__DIR__.DIRECTORY_SEPARATOR.'env.php') && include_once __DIR__.DIRECTORY_SEPARATOR.'env.php');
    
    //define the environment type and the source root
    (!defined('PHK_ENV')) && define('PHK_ENV', 'prod');
    (!defined('PHK_ROOT')) && define('PHK_ROOT', dirname(__DIR__).DIRECTORY_SEPARATOR);
    
    
    //create a temporary closure to define file paths
    $pathdef = function ($constant, $path) {
        if (!defined($constant)) {
            (!is_dir($path) && is_dir(PHK_ROOT.$path)) && $path = PHK_ROOT.$path;
            define($constant, realpath($path).DIRECTORY_SEPARATOR);
        }
    };
    
    //define the file paths
    $pathdef('CORE_PATH', 'php/core');
    $pathdef('APP_PATH', 'php/app');
    $pathdef('VIEW_PATH', 'php/app/views');
    $pathdef('PKG_PATH', 'php/pkg');
    $pathdef('LOG_PATH', 'logs');
    
    //the path definer has served its purpose
    unset($pathdef);
    
    
    //create a temporary closure to import core and (optionally) app classes
    empty($import) && $import = function ($class, $interface = false) use (&$import) {
        $interface && $import($class.DIRECTORY_SEPARATOR.$class.'Interface');
        
        if (is_file($path = CORE_PATH.'classes'.DIRECTORY_SEPARATOR.$class.'.php')) {
            require_once $path;
        }
        if (is_file($path = APP_PATH.'classes'.DIRECTORY_SEPARATOR.$class.'.php')) {
            require_once $path;
        }
    };
    
    //import the absolute basic core and app files
    $import('Singleton');
    $import('Exception');
    $import('Loader');
    $import('Bootstrap');
    
    //the import function should be replaced with the loader class after this point
    unset($import);
    
    
    //call the optional post-load / pre-run function
    function_exists('phork_initialize') && phork_initialize();
    
    
    //create a class alias to either the core class or the app class for each main component
    (!class_exists('PhorkException', false)) && class_alias('Phork\\Core\\Exception', 'PhorkException');
    (!class_exists('PhorkLoader', false))    && class_alias('Phork\\Core\\Loader', 'PhorkLoader');
    (!class_exists('Phork', false))          && class_alias('Phork\\App\\Bootstrap', 'Phork');
    
    //initialize the bootstrap, register the common object(s), initialize the app, and run everything
    try {
        Phork::instance()
            ->register('loader', PhorkLoader::instance(true))
            ->init(PHK_ENV)
            ->run()
            ->shutdown()
        ;
    } catch (Exception $exception) {
        require 'fatal.php';
    }
