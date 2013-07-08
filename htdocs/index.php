<?php
	//include any environmental configuration data (this can also be auto-prepended by the server)
	(file_exists('env.php') && include_once 'env.php');
	
	//define the environment type and the source root if they haven't already been set in env.php
	(!defined('PHK_ENV')) && define('PHK_ENV', 'prod');
	(!defined('PHK_ROOT')) && define('PHK_ROOT', realpath(dirname(__DIR__)).DIRECTORY_SEPARATOR);
	
	
	//create a temporary closure to define file paths
	$pathdef = function($constant, $path) {
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
	
	
	//create a temporary closure to import core and app files
	$import = function($path, $type = 'classes') {
		$path = str_replace('/', DIRECTORY_SEPARATOR, $path);
		
		if (is_file(CORE_PATH.$type.DIRECTORY_SEPARATOR.$path.'.php')) {
			require_once CORE_PATH.$type.DIRECTORY_SEPARATOR.$path.'.php';
		}
		
		if (is_file(APP_PATH.$type.DIRECTORY_SEPARATOR.$path.'.php')) {
			require_once APP_PATH.$type.DIRECTORY_SEPARATOR.$path.'.php';
		}
	};
	
	//import the absolute basic core and app files
	$import('singleton');
	$import('exception');
	$import('loader');
	$import('event');
	$import('bootstrap');
	
	//the import function should be replaced with the loader class after this point
	unset($import);
	
	
	//call the optional post-load / pre-run function
	function_exists('phork_initialize') && phork_initialize();
	
	
	//set up the exception and bootstrap class aliases
	(!class_exists('PhorkException')) && class_alias('Phork\\Core\\Exception', 'PhorkException');
	(!class_exists('Phork')) && class_alias('Phork\\App\\Bootstrap', 'Phork');
	
	//initialize the bootstrap, register the common objects, initialize the app, and run everything
	try {
		Phork::instance()
		     ->register('loader', class_exists('Phork\\App\\Loader', false) ? Phork\App\Loader::instance(true) : Phork\Core\Loader::instance(true))
		     ->register('event', class_exists('Phork\\App\\Event', false) ? Phork\App\Event::instance(true) : Phork\Core\Event::instance(true))
		     ->init(PHK_ENV)
		     ->run()
		     ->shutdown()
		;
	}
	
	//handle any uncaught exceptions with a basic fatal error page
	catch (Exception $exception) {
		require 'fatal.php';
	}