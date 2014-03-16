<?php
    namespace Phork\Core;

    /**
     * The singleton ensures that there can be only one instance of a
     * class and provides a global access point to that instance. This
     * also has the option to dereference the instance which means this
     * class will no longer store a static reference to it. This is to
     * be used with the registry so an object can be instantiated here,
     * saved to the registry, and then dereferenced here so that if the
     * registry object is unset there won't be a reference still hanging
     * around here preventing the object from being destroyed.
     *
     * The singleton is not a true singleton in that it is possible
     * (but not recommended) to destroy a singleton and re-initialize it.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
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
            if (!array_key_exists($class = get_called_class(), static::$instances)) {
                if (empty(static::$dereferenced[$class])) {
                    if ($create) {
                        static::$instances[$class] = new $class();
                    }
                } else {
                    throw new \PhorkException(sprintf('The singleton %s has been instantiated and dereferenced', $class));
                }
            }
            
            if (array_key_exists($class, static::$instances)) {
                $instance = static::$instances[$class];
                $dereference && static::dereference();
                
                return $instance;
            }
        }
        

        /**
         * Removes the reference to the instantiated singleton so that
         * unset() can be called and the object can be destroyed. This
         * keeps tracks of which objects have been dereferenced so they
         * can't be reinstantiated later.
         *
         * @access public
         * @return object The instance of the object
         * @static
         */
        static public function dereference()
        {
            if (array_key_exists($class = get_called_class(), static::$instances)) {
                $instance = static::$instances[$class];
                unset(static::$instances[$class]);
                static::$dereferenced[$class] = true;

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
         * Removes the dereferenced flag or the instance.
         *
         * @access public
         */
        public function __destruct()
        {
            if (array_key_exists($class = get_called_class(), static::$dereferenced)) {
                unset(static::$dereferenced[$class]);
            } elseif (array_key_exists($class, static::$instances)) {
                unset(static::$instances[$class]);
            }
        }


        /**
         * A singleton can't be cloned.
         *
         * @access protected
         */
        protected function __clone() {}
    }
