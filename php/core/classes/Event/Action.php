<?php
    namespace Phork\Core\Event;

    /**
     * The event action class holds a single action used with the event
     * class. An action consists of a callback, an array of arguments and
     * an optional run once flag.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core\Event
     */
    class Action
    {
        protected $callback;
        protected $args;
        protected $once;
        
        
        /**
         * Sets up the event action with the callback and args passed.
         *
         * @param callable $callback The closure, function name or method that will be triggered
         * @param array $args The array of arguments to be passed to the callback
         * @param boolean $once Whether to remove this event after it's been run
         * @return void
         */
        public function __construct($callback, array $args = array(), $once = false)
        {
            $this->callback = $callback;
            $this->args = $args;
            $this->once = $once;
        }
        
        
        /**
         * Returns (and optionally sets) the callback closure, function name
         * or method.
         *
         * @access public
         * @param callable The callback
         * @return callable The callback
         */
        public function callback($callback = null)
        {
            (!is_null($callback) && $this->callback = $callback);
            return $this->callback;
        }
        
        
        /**
         * Returns (and optionally sets) the arguments that get passed to the
         * callback.
         *
         * @access public
         * @param array The callback arguments
         * @return array The callback arguments
         */
        public function args($args = null)
        {
            (!is_null($args) && $this->args = $args);
            return $this->args;
        }
        
        
        /**
         * Returns (and optionally sets) the once-only flag.
         *
         * @access public
         * @param boolean The once-only flag
         * @return boolean The once-only flag
         */
        public function once($once = null)
        {
            (!is_null($once) && $this->once = $once);
            return $this->once;
        }
    }
