<?php
    namespace Phork\Core;

    /**
     * Dispatches all processing to the active handlers (AKA observers).
     *
     * <code>
     *   //initialize a new dispatcher and manually add and remove handlers
     *   $foo = new Foo();
     *   $foo->addHandler('bar', new Foo\Bar('some', 'args'));
     *   $foo->addHandler('baz', new Foo\Baz('some', 'more', 'args'), false);
     *   $foo->activateHandler('baz');
     *   $foo->removeHander('bar');
     *   $foo->passthru('content', 'to', 'dispatch');
     *
     *   //initialize a group of handlers based on a config file
     *   $foo->init(array(
     *      'bar' => array(
     *        'active'  => true,
     *        'class'   => '\Phork\Core\Foo\Bar',
     *        'params'  => array(
     *          'life'    => 42
     *        )
     *      ),
     *      'baz' => array(
     *        'active'  => false,
     *        'class'   => '\Phork\App\Foo\Baz'
     *      )
     *   ));
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    abstract class Dispatcher
    {
        protected $instanceOf;
        protected $minimum = 0;
        protected $maximum = null;

        protected $handlers = array();
        protected $active = array();
        protected $config = array();


        /**
         * Initializes the handlers based on the configuration passed.
         * This will overwrite the previous handlers and configurations.
         *
         * @access public
         * @param array The array of handler config data
         * @return array The number of handlers initialized and the number activated
         */
        public function init(array $config)
        {
            $this->config = $config;
            $this->handlers = $this->active = array();

            foreach ($config as $name => $handler) {
                if (!empty($handler['active'])) {
                    $this->initHandler($name);
                }
            }

            return array(
                count($this->handlers),
                count($this->active)
            );
        }
        

        /**
         * Initializes and adds a handler based on the set of config data.
         *
         * @access public
         * @param string $name The name of the handler to initialize
         * @param array $config The handler config or empty to default to the loaded config
         * @return void
         */
        public function initHandler($name, $config = array())
        {
            if ($config = $config ?: $this->config[$name]) {
                $reflection = new \ReflectionClass($config['class']);
                $this->addHandler($name, $reflection->newInstance(!empty($config['params']) ? $config['params'] : array()), !empty($config['active']));
            } else {
                throw new \PhorkException(sprintf('The %s handler in %s must be configured before being initialized', $name, get_class($this)));
            }
        }
        

        /**
         * Adds a new handler. Active handlers do the actual processing.
         *
         * @access public
         * @param string $name The name of the handler
         * @param object $handler The handler object
         * @param boolean $active Whether to activate the handler
         * @return void
         */
        public function addHandler($name, $handler, $active = true)
        {
            if (!$this->instanceOf || $handler instanceof $this->instanceOf) {
                $this->handlers[$name] = $handler;
                $this->active[$name] = !!$active;
            } else {
                throw new \PhorkException(sprintf('The %s handler must be an instance of %s', get_class($this), $this->instanceOf));
            }
        }
        

        /**
         * Removes a handler.
         *
         * @access public
         * @param string $name The name of the handler to remove
         * @param boolean $warn Whether to throw an exception if removing a non-existent handler
         * @return void
         */
        public function removeHander($name, $warn = true)
        {
            if (array_key_exists($name, $this->handlers)) {
                unset($this->handlers[$name], $this->active[$name], $this->config[$name]);
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Unable to remove non-existent handler %s from %s', $name, get_class($this)));
            }
        }
        

        /**
         * Activates a handler by name. If the handler isn't initialized this
         * will initialize it based on the config.
         *
         * @access public
         * @param string $name The name of the handler to activate
         * @return void
         */
        public function activateHandler($name)
        {
            if (empty($this->handlers[$name])) {
                $this->initHandler($name);
            }

            if (array_key_exists($name, $this->handlers)) {
                if (empty($this->handlers[$name])) {
                    $this->initHandler($name);
                }
                $this->active[$name] = true;
            } else {
                throw new \PhorkException(sprintf('Unable to activate non-existent handler %s from %s', $name, get_class($this)));
            }
        }


        /**
         * Deactivates a handler by name. Only active handlers are run.
         *
         * @access public
         * @param string $name The name of the handler to deactivate
         * @param boolean $warn Whether to throw an exception if deactivating a non-existent handler
         * @return void
         */
        public function deactivateHandler($name, $warn = true)
        {
            if (array_key_exists($name, $this->handlers)) {
                $this->active[$name] = false;
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Unable to deactivate non-existent handler %s from %s', $name, get_class($this)));
            }
        }


        /**
         * Gets a handler if it exists and returns it.
         *
         * @access public
         * @param string $name The name of the handler to get
         * @param boolean $warn Whether to throw an exception if getting a non-existent handler
         * @return object The handler object
         */
        public function getHandler($name, $warn = true)
        {
            if (array_key_exists($name, $this->handlers)) {
                return $this->handlers[$name];
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Unable to get non-existent handler %s from %s', $name, get_class($this)));
            }
        }


        /**
         * Dispatches the call to the handler(s) if the handlers are valid.
         * This does not check if a handler actually has the method being
         * called in case it's handled by another magic method. If more than
         * one handler can be registered this returns the results as an
         * array keyed by handler name. If only one handler is allowed this
         * returns the results normally.
         *
         * @access public
         * @param string $method The name of the method called
         * @param array $args The arguments passed to the method
         * @return mixed The return value from the handler(s)
         */
        public function __call($method, $args)
        {
            if (($total = array_sum($this->active)) >= $this->minimum && (!$this->maximum || $total <= $this->maximum)) {
                foreach ($this->handlers as $name => $handler) {
                    if (!empty($this->active[$name])) {
                        $results[$name] = call_user_func_array(array($handler, $method), $args);
                    }
                }

                if ($this->maximum == 1) {
                    $results = $results[$name];
                }

                return isset($results) ? $results : null;
            } else {
                throw new \PhorkException(sprintf('Invalid number of handlers defined for %s', get_class($this)));
            }
        }
    }
