<?php
    namespace Phork\Core\Encoder\Handlers;

    /**
     * Encodes an array or object to JSON data. This should be used
     * as a handler for the Encoder class.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Json implements HandlerInterface
    {
        /**
         * Sets up the handler's params if there are any.
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
        }


        /**
         * Encodes the data into JSON.
         *
         * @access public
         * @param mixed $source The array or object to encode
         * @param array $args Custom formatting options
         * @return string The JSON data.
         */
        public function encode($source, $args = array())
        {
            return json_encode($source, !empty($args['options']) ? $args['options'] : null);
        }


        /**
         * Returns the header to send for JSON data.
         *
         * @access public
         * @return string The header for JSON data
         */
        public function getHeader()
        {
            return 'Content-type: application/json';
        }
    }
