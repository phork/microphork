<?php
    namespace Phork\Core;

    /**
     * This is the base class for all API calls. It works nearly the same
     * as a controller except it returns the results in an array instead
     * of outputting them. This allows for the results to then be displayed
     * by the API controller or for this to be called internally.
     *
     * GET: /api/encoders.json
     * GET: /api/batch.json?requests=%7B"encoders"%3A%7B"method"%3A"get"%2C"url"%3A"%5C%2Fapi%5C%2Fencoders.json"%7D%7D
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Api
    {
        protected $prefix = 'handle';
        protected $restful = false;
        protected $segment = 1;

        protected $router;
        protected $authenticated;
        protected $internal;
        protected $format;

        protected $statusCode;
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
            
            if (!class_exists('\ApiException', false)) {
                $aliased = \Phork::loader()->loadStack(\Phork::LOAD_STACK, 'Api/Exception',
                    function ($result, $type) {
                        $class = sprintf('\\Phork\\%s\\Api\\Exception', $type);
                        return class_alias($class, 'ApiException');
                    }
                );
                
                if (!$aliased) {
                    throw new \PhorkException(\Phork::language()->translate('Unable to load API exception handler'));
                }
            }
        }

        /**
         * Determines whether to delegate handling to a separate object based
         * on the number of segments in the URL. The zero-based $segment property
         * determines where to start mapping the URL from. For example if the URL
         * is /api/encoders.json then $segment should be 1 so that /api/ will be
         * ignored. If the URL has more than $segment+1 segments then this will
         * instantiate a new API object using the $segment+1 value. For example
         * if the URL is /api/foo/bar.json then the handling will be delegated to
         * a new Api/Foo object. This allows for all API requests to be routed
         * through this class and for all fatal errors (eg. 404) to be handled
         * here instead of somewhere that won't return the results in the right 
         * format.
         *
         * @access public
         * @param integer $segment The segment to start mapping the router from
         * @return array The result data either to be encoded or handled as is
         */
        public function run($segment = null)
        {
            $this->segment = $segment ?: $this->segment;
            unset($segment);
            
            try {
                if (get_class($this) == __CLASS__ && count($segments = $this->router->getSegments()) > ($this->segment + 1)) {
                    $class = \Phork::loader()->loadStack(\Phork::LOAD_STACK, ($segment = ucfirst($segments[$this->segment])), (
                        function ($result, $type) use ($segment) {
                            $class = sprintf('\\Phork\\%s\\Api\\%s', $type, $segment);
                            return $class;
                        }
                    ), 'classes/Api');
    
                    if (!$class) {
                        throw new \ApiException(\Phork::language()->translate('Invalid API class'), 400);
                    }
                    
                    $delegate = new $class($this->router, $this->authenticated, $this->internal);
                } else {
                    $this->format = $this->router->getExtension();
                    $this->handle();
                }
            } catch (\ApiException $exception) {
                trigger_error($exception->getMessage(), E_USER_ERROR);
                $this->error($exception->getCode());
            }
            
            return isset($delegate) ? $delegate->run($this->segment + 1) : array(
                $this->statusCode ?: 200,
                $this->success,
                $this->result
            );
        }

        /**
         * Verifies that the actual method matches the method passed and
         * throws an API exception if it doesn't. This can accept multiple
         * arguments (eg. validate('GET', 'HEAD')).
         *
         * @access protected
         * @param string $method,... The required request type (GET, PUT, POST, DELETE, HEAD)
         * @return void
         */
        protected function validate($method)
        {
            $methods = array_map('strtolower', func_get_args());
            
            if (!in_array($this->router->getMethod(), $methods)) {
                throw new \ApiException(\Phork::language()->translate('Invalid request method - [%s] required', implode('|', $methods)), 400);
            }
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
            $prefix = $this->restful ? \Phork::router()->getMethod() : $this->prefix;
            $segment = str_replace('.'.$this->format, '', $this->router->getSegment($this->segment));
            
            if (method_exists($this, $method = $prefix.ucfirst($segment))) {
                call_user_func_array(array($this, $method), array_slice(\Phork::router()->getSegments(), $this->segment + 1));
            } else {
                throw new \ApiException(\Phork::language()->translate('Invalid API method'), 400);
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
                $this->result['errors'] = array_values($errors);
                \Phork::error()->clear();
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
        protected function handleBatch()
        {
            if (!($requests = $this->router->getVariable('requests'))) {
                throw new \ApiException(\Phork::language()->translate('Missing batch definitions'), 400);
            }
            
            if (!(is_string($requests) && $requests = json_decode($requests, true))) {
                throw new \ApiException(\Phork::language()->translate('Invalid batch definitions'), 400);
            }
            
            foreach ($requests as $key => $request) {
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
                            ) = Api\Internal::post($request['url'], !empty($request['args']) ? $request['args'] : array(), false);
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
                    throw new \ApiException(\Phork::language()->translate('Missing request type and/or URL'), 400);
                }
            }

            $this->success = true;
            $this->result = array(
                'batched' => isset($result) ? $result : array()
            );
        }

        /**
         * Returns the encoders available to format the results.
         *
         * @access protected
         * @return array The array of encoders
         */
        protected function handleEncoders()
        {
            $this->validate('GET', 'HEAD');
            $handlers = \Phork::config()->encoder->handlers;

            $this->success = true;
            $this->result = array(
                'encoders' => array_keys($handlers->export())
            );
        }

        //-----------------------------------------------------------------
        //   get and set methods
        //-----------------------------------------------------------------

        /**
         * Returns an XML node name based on the parent's name. This is to
         * prevent child nodes being named with a generic name.
         *
         * @access public
         * @param string $node The name of the node to potentially format
         * @param string $parent The name of the parent node
         * @return string The formatted node name
         */
        public function mapXmlNode($node, $parent)
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
