<?php
    namespace Phork\Core\Debug\Handlers;

    /**
     * Logs the debugging data to a file. This should be used as a
     * handler for the Debug class. The request ID is used to help
     * identify which logs are from the same request.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Log implements HandlerInterface
    {
        protected $logfile;
        protected $verbose;
        protected $request;

        
        /**
         * Sets up the handler's params including the log file path and
         * whether to be verbose. The request ID is used to group data for
         * a single request.
         *
         * @access public
         * @param array $params An array of params to set for each property
         * @return void
         */
        public function __construct($params = array())
        {
            foreach ($params as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }

            if (!$this->logfile) {
                throw new \PhorkException('Invalid debug log file');
            }

            $this->request = md5(rand());
        }


        /**
         * Builds the debugging string and logs the output. This can be
         * passed as many debugging params as necessary which will be
         * concatenated together.
         *
         * @access public
         * @return void
         */
        public function log()
        {
            $args = func_get_args();
            $output = implode(': ', $args);

            if ($this->verbose) {
                $output = '@ '.microtime(true).' '.\Phork::router()->getRelativeUrl()."\n".str_repeat(' ', 5).$output;
            }
            $output = '['.$this->request.'] '.$output;

            error_log("{$output}\n", 3, $this->logfile);
        }
    }
