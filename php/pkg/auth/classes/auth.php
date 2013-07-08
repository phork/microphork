<?php
	namespace Phork\Pkg;
	
	/**
	 * Loads an authentication system and delegates processing to it.
	 * This is a singleton.
	 *
	 * <code>
	 *   $auth = Auth();
	 *   $auth->addHandler('database', new Auth\Database());
	 *   $auth->isAuthenticated();
	 * </code>
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage auth
	 */
	class Auth extends \Phork\Core\Dispatcher {
		
		protected $instanceOf = '\\Phork\\Pkg\\Auth\\Interfaces\\Handler';
		
		protected $minimum = 1;
		protected $maximum = 1;
	}