<?php
	namespace Phork\Core\Encoder\Interfaces;
	
	/**
	 * The encoder handler interface makes sure each encoder
	 * handler has a proper encode method.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	interface Handler {
	
		public function __construct($params = array());
		public function encode($source, $args = array());
		public function getHeader();
	}