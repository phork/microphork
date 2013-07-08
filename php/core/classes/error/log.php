<?php
	namespace Phork\Core\Error;
	
	/**
	 * Logs the error messages to a file. This should be used as a
	 * handler for the Error class.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	class Log implements Interfaces\Handler {
		
		protected $logfile;
		protected $verbose;
		
		
		/**
		 * Sets up the handler's params including the log file path and
		 * whether to be verbose.
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
		 * Builds the error string and sends it to the logging method.
		 *
		 * @access public
		 * @param string $type The error type in plain text
		 * @param integer $level The error level
		 * @param string $error The error message
		 * @param string $file The file containing the error
		 * @param integer $line The line number of the error
		 * @return void
		 */
		public function handle($type, $level, $error, $file, $line) {
			$this->log($this->verbose ? sprintf('%s: %s in %s on line %d', $type, $error, $file, $line) : $error);
		}
		
		
		/**
		 * Builds the error message and logs the output. This has the
		 * option to display standard or verbose errors.
		 *
		 * @access protected
		 * @param string $output The full error message to log
		 * @return void
		 */
		protected function log($output) {
			if (!$this->logfile) {
				throw new \PhorkException('Invalid error log file');
			}
			error_log(date('m.d.y H:i:s')." {$output}\n", 3, $this->logfile);
		}
	}