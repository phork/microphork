<?php
	namespace Phork\Pkg\Auth\Interfaces;
	
	/**
	 * The auth handler interface makes sure each auth handler has
	 * a proper constructor as well as authentication methods and
	 * user data retrieval methods.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage auth
	 */
	interface Handler {
	
		public function __construct($params = array());
		
		public function standardAuth($username, $password);
		public function cookieAuth();
		public function logout();
		
		public function isAuthenticated();
		
		public function getUserId();
		public function getUserName();	
	}