<?php
    namespace Phork\Core;

    /**
     * The singleton ensures that there can be only one instance of a
     * class and provides a global access point to that instance. This
     * also has the option to dereference the instance which means this
     * class will no longer store a static reference to it. This can
     * be used with the registry so if an object is instantiated here,
     * saved to the registry, and then dereferenced here if the registry
     * object is unset there won't be a reference still hanging around
     * here preventing the object from being destroyed.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    abstract class Singleton
    {
        static protected $instances = array();
        static protected $dereferenced = array();


        /**
         * Returns the instance of the singleton object. If it doesn't
         * exist this instantiates it. Has the option to immediately
         * dereference the singleton. An object that has been instantiated
         * and dereferenced cannot be instantiated again.
         *
         * @access public
         * @param boolean $dereference Whether to immediately dereference the object
         * @param boolean $create Whether to create a new instance if one doesn't exist
         * @return object The instance of the object
         * @static
         */
        static public function instance($dereference = false, $create = true)
        {
            if (!array_key_exists($class = get_called_class(), self::$instances)) {
                if (empty(self::$dereferenced[$class])) {
                    if ($create) {
                        self::$instances[$class] = new $class();
                    }
                } else {
                    throw new \PhorkException(sprintf('The singleton %s has been instantiated and dereferenced', $class));
                }
            }
            
            if (array_key_exists($class, self::$instances)) {
                $instance = self::$instances[$class];
                $dereference && self::dereference();
                
                return $instance;
            }
        }
        

        /**
         * Removes the reference to the instantiated singleton so
         * that unset() can be called and the object can be destroyed.
         * This keeps tracks of which objects have been dereferenced
         * so they can't be reinstantiated later.
         *
         * @access public
         * @return object The instance of the object
         * @static
         */
        static public function dereference()
        {
            if (array_key_exists($class = get_called_class(), self::$instances)) {
                $instance = self::$instances[$class];
                unset(self::$instances[$class]);
                self::$dereferenced[$class] = true;

                return $instance;
            }
        }
        
        
        /**
         * The constructor can't be public for a singleton.
         *
         * @access protected
         */
        protected function __construct() {}
        
        
        /**
         * Removes the dereferend flag or the instance.
         *
         * @access public
         */
        public function __destruct() {
            $class = get_called_class();
            if (array_key_exists($class, self::$dereferenced)) {
                unset(self::$dereferenced[$class]);
            } else if (array_key_exists($class, self::$instances)) {
                unset(self::$instances[$class]);
            }
        }


        /**
         * A singleton can't be cloned.
         *
         * @access protected
         */
        protected function __clone() {}
    }
