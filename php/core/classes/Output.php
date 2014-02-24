<?php
    namespace Phork\Core;

    /**
     * The output class is used to buffer and send headers and content. It
     * can also be used to output content directly without buffering. Most 
     * public methods in here return the object itself to allow for daisy 
     * chaining calls. This is a singleton.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Output extends Singleton
    {
        protected $buffered;
        protected $callback;

        protected $statusCodes = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );

        
        /**
         * Called when the object is destroyed to make sure that everything
         * has been output.
         *
         * @access public
         * @return void
         */
        public function __destruct()
        {
            parent::__destruct();
            $this->flush();
        }


        /**
         * Turns on output buffering within this class. This is internal
         * buffering and doesn't affect the output buffer. If no output 
         * callback is passed then this will use an anonymous function 
         * because echo and print are language constructs and won't work. 
         *
         * @access public
         * @param callable $callback An optional custom output function
         * @return object The instance of the output object
         */
        public function buffer($callback = null)
        {
            if (!$this->buffered) {
                $this->buffered = true;
            }
            
            $this->callback = $callback ?: function ($buffered) {
                print $buffered;
            };
            
            return $this;
        }


        /**
         * Outputs the headers and the content, turns off buffering and
         * destroys the output events.
         *
         * @access public
         * @return object The instance of the output object
         */
        public function flush()
        {
            if ($this->buffered) {
                $this->outputHeaders();
                $this->outputContent();
            }
            
            $this->clear();
            return $this;
        }
        
        
        /**
         * Destroys any headers and content that have been buffered and
         * turns off buffering.
         *
         * @access public
         * @return object The instance of the output object
         */
        public function clear()
        {
            if ($this->buffered) {
                \Phork::event()->destroy('output.display.headers');
                \Phork::event()->destroy('output.display.content');
            
                $this->buffered = false;
                $this->callback = null;
            }
            
            return $this;
        }


        /**
         * Gets the status code string for the code passed.
         *
         * @access public
         * @param integer $statusCode The HTTP status code to get
         * @return string The status code string
         */
        public function getStatusCode($statusCode)
        {
            if (isset($this->statusCodes[$statusCode])) {
                return $this->statusCodes[$statusCode];
            }
        }


        //-----------------------------------------------------------------
        // builder methods
        //-----------------------------------------------------------------


        /**
         * Adds a header with the status code passed.
         *
         * @access public
         * @param integer $statusCode The HTTP status code to send
         * @return object The instance of the output object
         */
        public function setStatusCode($statusCode)
        {
            if ($statusCode) {
                $this->addHeader(sprintf('HTTP/1.0 %d %s', $statusCode, $this->statusCodes[$statusCode]));
            }

            return $this;
        }


        /**
         * Adds a header to the display.header event. This uses the built in
         * PHP header() function to output the results.
         *
         * @access public
         * @param string $header The complete header to send
         * @param integer $position The order to display the header in
         * @param string $id The unique ID of the header in the event object if output is buffered
         * @return object The instance of the output object
         */
        public function addHeader($header, $position = null, &$id = null)
        {
            if ($this->buffered) {
                \Phork::event()->once('output.display.headers', 'header', array($header), $position, $id);
            } else {
                header($header);
            }

            return $this;
        }


        /**
         * Includes a template. This uses the class method addContent() to
         * output the results if output buffering is turned on.
         *
         * @access public
         * @param string $path The absolute path to the template
         * @param array $templateVars The variables that the template can use
         * @param integer $position The order to display the content in
         * @param string $id The unique ID of the content in the event object if output is buffered
         * @return object The instance of the output object
         */
        public function addTemplate($path, $templateVars = null, $position = null, &$id = null)
        {
            if (is_array($templateVars)) {
                extract($templateVars);
            }
            
            if ($this->buffered) {
                ob_start();
            }

            if (($fullpath = \Phork::loader()->isTemplate($path)) && include($fullpath)) {
                if ($this->buffered && ($content = ob_get_clean())) {
                    $this->addContent($content, $position, $id);
                }
            } else {
                throw new \PhorkException(sprintf('Invalid template path (%s)', $path));
            }

            return $this;
        }


        /**
         * Adds some content to the display.content event. The benefit of using 
         * addContent versus standard print is that it makes it possible to 
         * rearrange or alter the content added.
         *
         * @access public
         * @param string $content The content to output
         * @param integer $position The order to display the content in
         * @param string $id The unique ID of the content in the event object if the content was buffered
         * @return object The instance of the output object
         */
        public function addContent($content, $position = null, &$id = null)
        {
            if ($this->buffered) {
                $id = \Phork::event()->once('output.display.content', $this->callback, array($content), $position, $id);
            } else {
                print $content;
            }

            return $this;
        }
        
        
        /**
         * Replaces the buffered content at the key passed using the
         * callback function which should return the new value.
         *
         * @access public
         * @param string $id The unique ID of the content in the event object if the content was buffered
         * @param callable $callback A closure, function name or method that accepts the current content and returns the new content
         * @return void
         */
        public function replaceContent($id, $callback)
        {
            if ($this->buffered) {
                \Phork::event()->replace('output.display.content', $id, $callback);
            } else {
                throw new \PhorkException('Output buffering must be turned on to replace content');
            }
        }


        //-----------------------------------------------------------------
        // output methods
        //-----------------------------------------------------------------


        /**
         * Triggers the event to display the queued headers.
         *
         * @access public
         * @return object The instance of the output object
         */
        public function outputHeaders()
        {
            \Phork::event()->trigger('output.display.headers');
            return $this;
        }


        /**
         * Triggers the event to display the queued content.
         *
         * @access public
         * @return object The instance of the output object
         */
        public function outputContent()
        {
            \Phork::event()->trigger('output.display.content');
            return $this;
        }
    }
