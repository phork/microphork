<?php
    namespace Phork\Core;

    /**
     * The event class is used to listen to reusable and once-only events 
     * throughout the application and to trigger the corresponding actions.
     * This is a singleton.
     *
     * <code>
     *   //listen for an event named foobar with one standard arg and one runtime arg
     *   Event::instance()->listen('foobar', function ($standard, $runtime) {
     *     print $standard."\n".$runtime."\n";
     *   }, array('This is a standard variable'));
     *
     *   //trigger the foobar event and pass the runtime args to it
     *   Event::instance()->trigger('foobar', array('This is a runtime variable'));
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core\Event
     */
    class Event extends Singleton
    {
        protected $events = array();
        
        const CALLBACK_KEY = 0;
        const ARGS_KEY = 1;
        const ONCE_KEY = 2;

        
        /**
         * Checks if an event exists.
         *
         * @access public
         * @param string $name The name of the event to check for
         * @return boolean True if it exists
         */
        public function exists($name)
        {
            return array_key_exists($name, $this->events);
        }
        

        /**
         * Registers the event action by storing it in the events array.
         * The action consists of an event name, a callback function, and
         * an optional array of arguments to pass to the callback function.
         *
         * @access public
         * @param string $name The name of the event
         * @param callable $callback The closure, function name or method that will be triggered
         * @param array $args The array of arguments to be passed to the callback
         * @param integer $position The position to insert the action in, otherwise it'll be last
         * @param string $id The unique ID to assign to the event
         * @param boolean $once Whether to remove this event after it's been run
         * @return string The unique key of the event action
         */
        public function listen($name, $callback, array $args = array(), $position = null, $id = null, $once = false)
        {
            ($this->exists($name) || $this->events[$name] = new Iterators\Associative());

            if ($position !== null) {
                return $this->events[$name]->insert($position, array($id, array($callback, $args, $once)));
            } else {
                return $this->events[$name]->append(array($id, array($callback, $args, $once)));
            }
        }
        

        /**
         * Registers an event to run only once. This is a convenience wrapper
         * for the listen method.
         *
         * @access public
         * @param string $name The name of the event
         * @param callable $callback The closure, function name or method that will be triggered
         * @param array $args The array of arguments to be passed to the callback
         * @param integer $position The position to insert the action in, otherwise it'll be last
         * @param string $id The unique ID to assign to the event
         * @return string The unique key of the event action
         */
        public function once($name, $callback, array $args = array(), $position = null, $id = null)
        {
            return $this->listen($name, $callback, $args, $position, $id, true);
        }


        /**
         * Runs the event actions registered with the event name passed.
         * Has the option to throw an exception if no event with that name
         * exists. If any of the event actions return a value it's added
         * to the results array.
         *
         * @access public
         * @param string $name The name of the registered event
         * @param array $args Any additional arguments to send to the callbacks
         * @param boolean $flush Whether to destroy the event after running it
         * @param boolean $warn Whether to throw an exception if the event doesn't exist
         * @return array The array of result data from the events
         */
        public function trigger($name, $args = null, $flush = false, $warn = false)
        {
            if ($iterator = $this->get($name, $warn)) {
                $iterator->rewind();
                
                $remove = $results = array();
                
                while (list($key, $action) = $iterator->each()) {
                    $results[$key] = $this->callback($action, $args);
                    (!empty($action[static::ONCE_KEY]) && array_push($remove, $key));
                }
    
                foreach ($remove as $key) {
                    $iterator->keyUnset($key);
                }
    
                ($flush && $this->destroy($name));
                
                return $results;
            }
        }
        
        
        /**
         * Calls the event callback function and passes the standard and
         * runtime args.
         *
         * @access protected
         * @param array $event The event array consisting of callback, standard args, and optionally a run once flag
         * @param array $args The optional runtime args to pass to the callback
         * @return mixed The results from the callback
         */
        protected function callback(array $event, $args)
        {
            return call_user_func_array($event[static::CALLBACK_KEY], is_array($args) ? array_merge($event[static::ARGS_KEY], $args) : $event[static::ARGS_KEY]);
        }


        /**
         * Removes the event from the registry and returns the registered
         * actions from the destroyed event.
         *
         * @access public
         * @param string $name The name of the event to remove
         * @param boolean $warn Whether to throw an exception if the event doesn't exist
         * @return object The iterator object containing the event actions
         */
        public function destroy($name, $warn = false)
        {
            if ($iterator = $this->get($name, $warn)) {
                unset($this->events[$name]);
                return $iterator;
            }
        }


        /**
         * Removes a single action from an event and returns the action.
         *
         * @access public
         * @param string $name The name of the event contain the action to remove
         * @param string $key The key of the action to remove
         * @param boolean $warn Whether to throw an exception if the event doesn't exist
         * @return array The event array of callback, args, and the run once flag
         */
        public function remove($name, $key, $warn = false)
        {
            if ($iterator = $this->get($name, $warn)) {
                if ($iterator->seek($key)) {
                    $action = $iterator->current();
                    $iterator->remove();
                    $iterator->rewind();
                } else {
                    throw new \PhorkException(sprintf('Unable to remove non-existent action %s from %s', $key, $name));
                }
                
                return $action;
            }
        }
        
        
        /**
         * Returns the event iterator for the event named.
         *
         * @access public
         * @param string $name The name of the event to return
         * @param boolean $warn Whether to throw an exception if the event doesn't exist
         * @return object The event iterator if it exists
         */
        public function get($name, $warn = false)
        {
            if ($this->exists($name)) {
                return $this->events[$name];
            } elseif ($warn) {
                throw new \PhorkException(sprintf('No event named %s has been registered', $name));
            }
        }
    }
