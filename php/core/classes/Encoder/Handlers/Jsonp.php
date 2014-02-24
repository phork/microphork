<?php
    namespace Phork\Core\Encoder\Handlers;

    /**
     * Encodes an array or object to JSONP data. This should be used
     * as a handler for the Encoder class.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Jsonp extends Json
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
         * Encodes the data into JSON and formats appropriately for a script
         * tag. The args must contain the name of the callback to include in
         * the result.
         *
         * @access public
         * @param mixed $source The array or object to encode
         * @param array $args Custom formatting options, including the callback
         * @return string The JSONP data.
         */
        public function encode($source, $args = array())
        {
            $json = json_encode($source, !empty($args['options']) ? $args['options'] : null);
            $callback = preg_replace('/[^a-z0-9_\.]/i', '', $args['callback']);

            return $callback.'('.$json.')';
        }


        /**
         * Returns the header to send for JSONP data.
         *
         * @access public
         * @return string The header for JSONP data
         */
        public function getHeader()
        {
            return 'Content-type: application/javascript';
        }
    }
