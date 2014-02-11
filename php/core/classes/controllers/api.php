<?php
    namespace Phork\Core\Controllers;

    /**
     * The controller handles the user input and sends back the response.
     * This is the default API controller. It encodes an array of result
     * data into the format specified by the URL extension. This is used
     * to dispatch to the Api classes and doesn't usually need extending.
     * All core controllers must be extended by an app controller.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    abstract class Api
    {
        const URL_TOKEN = 't';

        protected $withMeta;
        protected $format;
        protected $success;
        protected $result;
        protected $statusCode;
        protected $api;


        /**
         * Dispatches processing to the core API handler and displays the
         * result.
         *
         * @access public
         * @return void
         */
        public function run()
        {
            \Phork::event()->trigger('controller.run.before', null, true);

            $class = \Phork::loader()->loadStack(\Phork::LOAD_STACK, 'api',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Api', ucfirst($type));
                    return $class;
                }
            );

            $this->api = new $class(\Phork::router(), $this->authenticate(), false);
            $this->format = \Phork::router()->getExtension() ?: \Phork::config()->interfaces->api->defaults->encoder;
            $this->withMeta = \Phork::config()->interfaces->api->defaults->meta;

            try {
                list(
                    $this->statusCode,
                    $this->success,
                    $this->result
                ) = $this->api->run();
            } catch (\Exception $exception) {
                $this->success = false;
                $this->statusCode = $exception->getCode();
                $this->result = array('error' => $exception->getMessage());
            }

            $this->output();

            \Phork::event()->trigger('controller.run.after', null, true);
        }


        /**
         * Authenticates a user using either the HTTP authentication or
         * token authentication. The token can either be part of the query
         * string or passed with a POST request.
         *
         * @access protected
         * @return boolean True if the user was authenticated
         */
        protected function authenticate()
        {
            ($config = \Phork::config()->get('api')) && ($internal = $config->internal);

            if (empty($internal)) {
                if (!empty(\Phork::instance()->auth)) {
                    if (\Phork::auth()->isTokenValid(\Phork::router()->getVariable(static::URL_TOKEN))) {
                        return \Phork::auth()->isAuthenticated();
                    } elseif (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
                        return \Phork::auth()->standardAuth($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
                    }
                }
            }
        }


        /**
         * Encodes and outputs the result returned from the API handler.
         *
         * @access protected
         * @return void
         */
        protected function output()
        {
            try {
                list($header, $content) = $this->encode($this->success, $this->result);
                
                \Phork::output()
                    ->setStatusCode($this->statusCode ?: 200)
                    ->addHeader($header)
                    ->addContent($content)
                ;
            } catch (\Exception $exception) {
                \Phork::instance()->fatal($exception->getCode() ?: 500, $exception->getMessage(), $exception);
            }
        }


        /**
         * Encodes the display result based on the extension.  Currently supports
         * XML, JSON, and JSONP.
         *
         * @access protected
         * @param boolean $success True if the output is a result of a successful operation
         * @param array $result The result array to encode
         * @return string The encoded string
         */
        protected function encode($success, array $result)
        {
            $encoder = \Phork::loader()->loadStack(\Phork::LOAD_STACK, 'encoder',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Encoder', ucfirst($type));
                    return new $class();
                }
            );

            $config = \Phork::config()->get('encoder');
            if ($config->handlers && $handlers = $config->handlers->export()) {
                $encoder->init($handlers);

                switch ($this->format) {
                    case 'xml':
                        $encoder->activateHandler('xml');
                        $args = array(
                            'formatCallback' => method_exists($this->api, 'getXmlNode') ? array($this->api, 'getXmlNode') : null
                        );
                        $handler = 'xml';
                        break;

                    case 'jsonp':
                        $encoder->activateHandler('jsonp');
                        $args = array(
                            'callback' => \Phork::router()->getVariable('callback')
                        );
                        $handler = 'jsonp';
                        break;

                    case 'json':
                        $encoder->activateHandler('json');
                        $args = array(
                            'options' => 0
                        );
                        $handler = 'json';
                        break;

                    default:
                        throw new \PhorkException(\Phork::language()->translate('Invalid encoder: %s', htmlentities($this->format) ?: 'undefined'), 400);
                }

                $header = $encoder->getHeader();
                $content = $encoder->encode(!$this->withMeta ? $result : array_merge(array(
                    'meta' => array(
                        'success' => !!$success
                    )
                ), $result), $args);

                return array(
                    $header[$handler],
                    $content[$handler]
                );
            }
        }
    }
