<?php
    namespace Phork\Core;

    /**
     * The config class is used to store and retrieve configuration vars
     * rather than having globals or constants scattered around. Config
     * objects are recursive.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Config
    {
        protected $config = array();

        /**
         * The constructor sets up the initial configuration data.
         *
         * @access public
         * @param array $config The initial config data
         */
        public function __construct(array $config = array())
        {
            $this->import($config);
        }


        /**
         * Imports an array of config data into the object.
         *
         * @access public
         * @param array $config The config data to import
         * @return void
         */
        public function import(array $config)
        {
            foreach ($config as $name => $value) {
                $this->set($name, $value);
            }
        }


        /**
         * Exports the entire config tree as an array.
         *
         * @access public
         * @return array The array of config data
         */
        public function export()
        {
            $result = array();
            foreach ($this->config as $name => $value) {
                if (is_object($value) && $value instanceof self) {
                    $result[$name] = $value->export();
                } else {
                    $result[$name] = $value;
                }
            }

            return $result;
        }


        /**
         * Loads a stack of config files into the object. Each config file
         * should return an array of config vars. The $self = $this hack is
         * only necessary in PHP < 5.4.
         *
         * @access public
         * @param string $name The name of the file to load
         * @param string $stack The name of the load stack if not the default stack
         * @return void
         */
        public function load($name, $stack = null)
        {
            $self = $this;
            \Phork::loader()->loadStack($stack ?: \Phork::LOAD_STACK, $name, (
                function ($config, $type) use ($self) {
                    $self->import($config);
                }
            ), 'config', true);
        }
        

        /**
         * Returns either the existing config variable or the default value
         * if no config is defined. Also has the option to throw an exception
         * if the value isn't defined.
         *
         * @access public
         * @param string $name The name of the variable to get
         * @param mixed $default The default value to return if no config is set
         * @param boolean $warn Whether to throw an exception if the variable doesn't exist
         * @return mixed The config value
         */
        public function get($name, $default = null, $warn = false)
        {
            $result = $default;
            if (array_key_exists($name, $this->config)) {
                $result = $this->config[$name];
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Invalid config: %s', $name));
            }

            return $result;
        }
        

        /**
         * Sets a config variable. If the merge flag is set this will merge
         * the new data with any existing data. Otherwise it will overwrite it.
         *
         * @access public
         * @param string $name The name of the variable to set
         * @param mixed $value The value to set the variable to
         * @param boolean $merge Whether to merge with any existing data
         * @return mixed The new config value
         */
        public function set($name, $value, $merge = true)
        {
            if (is_array($value)) {
                if ($merge && array_key_exists($name, $this->config) && $this->config[$name] instanceof self) {
                    $this->merge($this->config[$name], $value);
                } else {
                    $this->config[$name] = new static($value);
                }
            } else {
                $this->config[$name] = $value;
            }

            return $this->config[$name];
        }
        
        
        /**
         * Deletes a config variable. Also has the option to throw an exception
         * if the value isn't defined.
         *
         * @access public
         * @param string $name The name of the variable to delete
         * @param boolean $warn Whether to throw an exception if the variable doesn't exist
         * @return void
         */
        public function delete($name, $warn = false)
        {
            if (array_key_exists($name, $this->config)) {
                unset($this->config[$name]);
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Invalid config: %s', $name));
            }
        }
        

        /**
         * Merges the additional values into the initial object.
         *
         * @access protected
         */
        protected function merge(Config $initial, array $additional)
        {
            foreach ($additional as $name => $value) {
                $initial->set($name, $value);
            }
        }


        //-----------------------------------------------------------------
        //   magic methods
        //-----------------------------------------------------------------


        /**
         * Called when an unknown or un-public variable is called. Returns
         * the config value by name.
         *
         * @access public
         * @return mixed The config value
         */
        public function __get($name)
        {
            return $this->get($name, null, true);
        }
        
        
        /**
         * Checks if an unknown or un-public variable exists.
         *
         * @access public
         * @return boolean True if the value is set
         */
        public function __isset($name)
        {
            return array_key_exists($name, $this->config);
        }
    }
