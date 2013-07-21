<?php
    namespace Phork\Core\Debug;

    /**
     * Displays the debugging data on the screen. This should be used
     * as a handler for the Debug class.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Display implements Interfaces\Handler
    {
        protected $html;
        protected $verbose;

        
        /**
         * Sets up the handler's params including whether the debugging
         * display should use an HTML delimiter.
         *
         * @access public
         * @param array $params An array of params to set for each property
         * @return void
         */
        public function __construct($params = array())
        {
            foreach ($params as $key=>$value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }


        /**
         * Builds the debugging string and prints the output. This can be
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
                $output = '['.number_format(microtime(true), 4, '.', '').'] '.$output;
            }

            $delimiter = ($this->html ? "<br />\n" : "\n");
            print $output.$delimiter;
        }
    }
