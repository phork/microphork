<?php
	namespace Phork\Core\Api;
	
	/**
	 * Calls local API methods by URL without having to go through an actual
	 * HTTP call by faking the URL object and calling the API controller 
	 * directly.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	class Internal {
	
		/**
		 * Spoofs an API request and retrieves the result without having
		 * the overhead of an extra HTTP request. If the status code 
		 * returned is in the error range (>= 400) but no new errors 
		 * have been triggered this will trigger a generic error.
		 *
		 * @access protected
		 * @param object $router The URL object set up with the URL of the API call 
		 * @param boolean $internal Whether special internal methods are allowed
		 * @return array An array with the status code, success flag and result data
		 * @static
		 */
		static protected function request(\Phork\Core\Router $router, $internal = true) {
			$authenticated = !empty(\Phork::instance()->auth) ? \Phork::auth()->isAuthenticated() : false;
			$errors = count(\Phork::error()->getErrors()->items());
			
			$api = \Phork::loader()->loadStack(\Phork::LOAD_STACK, 'api',
				function($result, $type) use ($router, $authenticated, $internal) {
					$class = sprintf('\\Phork\\%s\\Api', ucfirst($type));
					return new $class($router, $authenticated, $internal);
				}
			);
			
			list(
				$statusCode,
				$success,
				$result
			) = $api->run();
			
			if ($statusCode >= 400 && count(\Phork::error()->getErrors()) <= $errors) {
				trigger_error(\Phork::language()->translate('Undefined error'), E_USER_ERROR);
			}
			
			return array($statusCode, $success, $result);
		}
		
		
		//-----------------------------------------------------------------
		//   spoof methods
		//-----------------------------------------------------------------
		
		
		/**
		 * Spoofs an API get and retrieves the result without having the
		 * overhead of an extra HTTP request.
		 *
		 * @access public
		 * @param string $url The URL to get the API data from
		 * @param boolean $internal Whether special internal methods are allowed
		 * @return array An array with the success flag and the results
		 * @static
		 */
		static public function get($url, $internal = true) {
			if (strstr($url, '?') !== false) {
				list($url, $queryString) = explode('?', $url);
				parse_str($queryString, $variables);
			}
			
			$router = clone \Phork::router();
			$router->init('GET', $url, isset($variables) ? $variables : array());
			
			return static::request($router, $internal);
		}
		
		
		/**
		 * Spoofs an API post and retrieves the result without having the
		 * overhead of an extra HTTP request.
		 * 
		 * @access public
		 * @param string $url The URL to get the API data from
		 * @param array $post The data to post to the API
		 * @param boolean $internal Whether special internal methods are allowed
		 * @return array An array with the success flag and the results
		 * @static
		 */
		static public function post($url, $post, $internal = true) {
			$router = clone \Phork::router();
			$router->init('POST', $url, $post);
			
			return static::request($router, $internal);
		}
		
		
		/**
		 * Spoofs an API put and retrieves the result without having the
		 * overhead of an extra HTTP request.
		 *
		 * @access public
		 * @param string $url The URL to get the API data from
		 * @param boolean $internal Whether special internal methods are allowed
		 * @return array An array with the success flag and the results
		 * @static
		 */
		static public function put($url, $internal = true) {
			$router = clone \Phork::router();
			$router->init('PUT', $url, array());
			
			return static::request($router, $internal);
		}
		
		
		/**
		 * Spoofs an API delete and retrieves the result without having the
		 * overhead of an extra HTTP request.
		 *
		 * @access public
		 * @param string $url The URL to get the API data from
		 * @param boolean $internal Whether special internal methods are allowed
		 * @return array An array with the success flag and the results
		 * @static
		 */
		static public function delete($url, $internal = true) {
			$router = clone \Phork::router();
			$router->init('DELETE', $url, array());
			
			return static::request($router, $internal);
		}
	}