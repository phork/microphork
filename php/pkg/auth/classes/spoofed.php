<?php
	namespace Phork\Pkg\Auth;
	
	/**
	 * Spoofs the user authentication with hard coded data. This should
	 * be used as a handler for the Auth class.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage auth
	 */
	class Spoofed implements Interfaces\Handler {
	
		protected $authenticated;
		protected $userid;
		protected $username;
		
	
		/**
		 * Sets up the handler's params including the user account data.
		 *
		 * @access public
		 * @param array $params An array of params to set for each property
		 * @return void
		 */
		public function __construct($params = array()) {
			foreach ($params as $key=>$value) {
				if (property_exists($this, $key)) {
					$this->$key = $value;
				}
			}
		}
		
		
		/**
		 * Authenticates the user by username and password.
		 *
		 * @access public
		 * @param string $username The username to authenticate
		 * @param string $password The associated password
		 * @return boolean True if authenticated successfully
		 */
		public function standardAuth($username, $password) {
			return true;
		}
		
		
		/**
		 * Authenticates the user by cookie.
		 *
		 * @access public
		 * @return boolean True if authenticated successfully
		 */
		public function cookieAuth() {
			return true;
		}
		
		
		/**
		 * Logs out a user and clears all the cookies.
		 *
		 * @access public
		 * @return boolean True if logged out successfully
		 */
		public function logout() {
			return true;
		}
		
		
		/**
		 * Returns whether or not the user is authenticated.
		 *
		 * @access public
		 * @return boolean True if authenticated
		 */
		public function isAuthenticated() {
			return $this->authenticated;
		}
		
		
		/**
		 * Returns the user's ID.
		 *
		 * @access public
		 * @return integer The user ID
		 */
		public function getUserId() {
			return $this->userid;
		}
		
		
		/**
		 * Returns the user's username.
		 *
		 * @access public
		 * @return string The username
		 */
		public function getUserName() {
			return $this->username;
		}
	}