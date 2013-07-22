<?php
    namespace Phork\Core;

    /**
     * This is the base class for all API calls. It works nearly the same
     * as a controller except it returns the results in an array instead
     * of outputting them. This allows for the results to then be displayed
     * by the API controller or for this to be called internally.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Api
    {
        protected $prefix = 'handle';

        protected $router;
        protected $authenticated;
        protected $internal;
        protected $format;

        protected $statusCode = 200;
        protected $success = false;
        protected $result = array();


        /**
         * Sets up the router object containing the URL of the API request,
         * the flag that determine whether this is an internal API call
         * which will grant additional permissions, and whether the user
         * user is authenticated.
         *
         * @access public
         * @param object $router The URL object set up with the URL of the API call
         * @param boolean $authenticated Whether the user is authenticated
         * @param boolean $internal Whether this is an internal API call
         */
        public function __construct(Router $router, $authenticated = false, $internal = false)
        {
            $this->router = $router;
            $this->authenticated = $authenticated;
            $this->internal = $internal;
        }


        /**
         * Determines whether to delegate handling to a separate object based
         * on the number of segments in the URL. If the URL has more than 2
         * segments this will instantiate a new API object using the second
         * segment's value. This allows all API requests to be routed through
         * this class and for all fatal errors (eg. 404) to be handled here
         * instead of somewhere that won't return the results in the right
         * format.
         *
         * @access public
         * @return array The result data either to be encoded or handled as is
         */
        public function run()
        {
            if (get_class($this) == __CLASS__ && count($segments = $this->router->getSegments()) > 2) {
                $class = \Phork::loader()->loadStack(\Phork::LOAD_STACK, ($segment = $segments[1]), (
                    function($result, $type) use ($segment) {
                        $class = sprintf('\\Phork\\%s\\Api\\%s', ucfirst($type), ucfirst($segment));

                        return $class;
                    }
                ), 'classes/api');

                if ($class) {
                    $delegate = new $class($this->router, $this->authenticated, $this->internal);
                } else {
                    trigger_error(\Phork::language()->translate('Invalid API class', E_USER_ERROR));
                    $this->error(404);
                }
            } else {
                $this->format = $this->router->getExtension();
                $this->handle();
            }

            return isset($delegate) ? $delegate->run() : array(
                $this->statusCode,
                $this->success,
                $this->result
            );
        }


        /**
         * Verifies that the actual method matches the method passed.
         *
         * @access protected
         * @param string $method The required request type (GET, PUT, POST, DELETE)
         * @return boolean True on success
         */
        protected function validate($method)
        {
            if (!($result = ($this->router->getMethod() == strtolower($method)))) {
                trigger_error(\Phork::language()->translate('Invalid request method - %s required', $method), E_USER_ERROR);
            }

            return $result;
        }


        /**
         * Maps the API method to a method within this controller and
         * returns the response.
         *
         * @access protected
         * @return void
         */
        protected function handle()
        {
            $handlers = array(
                'batch'     => 'getBatch',
                'encoders'  => 'getEncoders'
            );

            $segment = str_replace('.'.$this->format, '', $this->router->getSegment(1));
            if (!empty($handlers[$segment])) {
                $method = $this->prefix.$handlers[$segment];
                $this->$method();
            } else {
                trigger_error(\Phork::language()->translate('Invalid API method', E_USER_ERROR));
                $this->error(404);
            }
        }


        /**
         * Sets up an error response.
         *
         * @access public
         * @param integer $statusCode The HTTP status code
         * @return void
         */
        public function error($statusCode = 400)
        {
            $this->statusCode = $statusCode;
            $this->success = false;
            $this->result = array();

            if ($errors = \Phork::error()->getErrors()->items()) {
                $this->result['errors'] = $errors;
            }
        }


        //-----------------------------------------------------------------
        //   handler methods
        //-----------------------------------------------------------------


        /**
         * Handles batch processing. Multiple API calls can be called at
         * once. The request data must be in JSON format. Batch API calls
         * can't take advantage of the internal flag.
         *
         * @access protected
         * @return array The array of result data for each call
         */
        protected function handleGetBatch()
        {
            if ($requests = $this->router->getVariable('requests')) {
                if ($requests = json_decode($requests, true)) {
                    foreach ($requests as $key=>$request) {
                        $key = isset($request['key']) ? $request['key'] : $key;
                        if (!empty($request['method']) && !empty($request['url'])) {
                            switch (strtolower($request['method'])) {
                                case 'get':
                                    list(
                                        $result[$key]['status'],
                                        $result[$key]['success'],
                                        $result[$key]['data'],
                                    ) = Api\Internal::get($request['url'], false);
                                    break;

                                case 'post':
                                    list(
                                        $result[$key]['status'],
                                        $result[$key]['success'],
                                        $result[$key]['data'],
                                    ) = Api\Internal::post($request['url'], $request['args'], false);
                                    break;

                                case 'put':
                                    list(
                                        $result[$key]['status'],
                                        $result[$key]['success'],
                                        $result[$key]['data'],
                                    ) = Api\Internal::put($request['url'], false);
                                    break;

                                case 'delete':
                                    list(
                                        $result[$key]['status'],
                                        $result[$key]['success'],
                                        $result[$key]['data'],
                                    ) = Api\Internal::delete($request['url'], false);
                                    break;
                            }
                        } else {
                            trigger_error(\Phork::language()->translate('Missing request type and/or URL'), E_USER_ERROR);
                            $this->error();
                        }
                    }

                    $this->success = true;
                    $this->result = array(
                        'batched' => isset($result) ? $result : array()
                    );
                } else {
                    trigger_error(\Phork::language()->translate('Invalid batch definitions'), E_USER_ERROR);
                    $this->error(400);
                }
            } else {
                trigger_error(\Phork::language()->translate('Missing batch definitions'), E_USER_ERROR);
                $this->error(400);
            }
        }


        /**
         * Returns the encoders available to format the results.
         *
         * @access protected
         * @return array The array of encodings
         */
        protected function handleGetEncoders()
        {
            if ($this->validate('GET')) {
                $config = \Phork::config()->get('encoder');

                $this->success = true;
                $this->result = array(
                    'encoders' => array_keys($config->handlers->export())
                );
            } else {
                $this->error(400);
            }
        }


        //-----------------------------------------------------------------
        //   get and set methods
        //-----------------------------------------------------------------


        /**
         * Used to get an XML node name based on the parent's name. This is to
         * prevent child nodes being named with a generic name.
         *
         * @access public
         * @param string $node The name of the node to potentially format
         * @param string $parent The name of the parent node
         * @return string The formatted node name
         */
        public function getXmlNode($node, $parent)
        {
            switch ($parent) {
                case 'errors':
                    $node = 'error';
                    break;

                case 'batched':
                    $node = 'result';
                    break;

                case 'encoders':
                    $node = 'ext';
                    break;
            }

            return $node;
        }
    }
