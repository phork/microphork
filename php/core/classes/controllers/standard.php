<?php
	namespace Phork\Core\Controllers;
	
	/**
	 * The controller handles the user input and sends back the response.
	 * All core controllers must be extended by an app controller. Most
	 * app controllers should extend this one. 
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	abstract class Standard {
	
		protected $prefix = 'display';
		protected $views = '';
		
		
		/**
		 * This is called from the bootstrap and it handles any necessary 
		 * processing before calling the display method. Generally this sets
		 * up the type of content to display based on the URL. The simplest
		 * use is to map /foo/bar/ to the Foo controller and displayBar()
		 * method. This also passes each subsequent URL segment as a function
		 * argument.
		 *
		 * @access public
		 * @return void
		 */
		public function run() {
			\Phork::event()->trigger('controller.run.before', null, true);
			
			$segment = (\Phork::router()->getSegment(1) ?: 'index');
			if (method_exists($this, $method = $this->prefix.ucfirst($segment))) {
				call_user_func_array(array($this, $method), array_slice(\Phork::router()->getSegments(), 2));
			} else {
				$this->displayFatal(404);
			}
			
			\Phork::event()->trigger('controller.run.after', null, true);
		}
		
		
		/**
		 * Adds the index template to the output.
		 *
		 * @access protected
		 * @return void
		 */
		protected function displayIndex() {
			\Phork::output()->addTemplate($this->views.'index');
		}
		
		
		/**
		 * Displays a fatal error by routing the call through the bootstrap's
		 * fatal error method. By adding the following route it's possible to 
		 * go directly to a specific error page (eg. /error/404/, or /error/)
		 * '^/error/([0-9]{3}/?)?' => '/home/fatal/$1'
		 *
		 * @access protected
		 * @return void
		 */
		protected function displayFatal($statusCode = null) {
			\Phork::instance()->fatal($statusCode);
		}
	}