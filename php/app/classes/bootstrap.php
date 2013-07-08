<?php
	namespace Phork\App;
	
	/**
	 * Includes the config files, the global classes and initializes the 
	 * application. This is a singleton.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage app
	 */
	class Bootstrap extends \Phork\Core\Bootstrap {
	
		/**
		 * Dispatches to the other initialization methods. This has 
		 * special handling to turn off debugging output for the API.
		 *
		 * @access public
		 * @param string $env The environment to initialize (eg. prod, stage, dev)
		 * @return object The instance of the bootstrap object
		 */
		public function init($env) {
			parent::init($env);
			
			if ($this->router->getSegment(0) == 'api') {
				$this->debug->deactivateHandler('display', false);
			}
			
			//initialize the user authentication object
			//$this->initAuth();
			
			return $this;
		}
		
		
		/**
		 * Initializes the user authentication system and adds it to
		 * the registry. The auth system is a package so it uses its
		 * own load stack.
		 *
		 * @access public
		 */
		public function initAuth() {
			
			//create a new load stack that loads from pkg then app
			$this->loader->addStack('auth', array(
				'pkg' => PKG_PATH.'auth'.DIRECTORY_SEPARATOR,
				'app' => APP_PATH
			));
			
			//load the configurations from the auth load stack
			$this->config->load('auth', 'auth');
			$config = $this->config->get('auth');
			
			//load and register an auth object from the auth load stack
			$this->register('auth', $this->loader->loadStack('auth', 'auth',
				function($result, $type) {
					$class = sprintf('\\Phork\\%s\\Auth', ucfirst($type));
					return new $class();
				}
			), true);
			
			//initialize the auth handler
			if ($config->handlers && $handlers = $config->handlers->export()) {
				$this->auth->init($handlers);
			}
			
			//remove the auth stack
			$this->loader->removeStack('auth');
		}
	}