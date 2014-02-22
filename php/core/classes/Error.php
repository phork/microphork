<?php
    namespace Phork\Core;

    /**
     * The error class is used as the default error handler. It can
     * also have additional handlers added to it for things like
     * logging to a file.
     *
     * <code>
     *   $debug = new Error();
     *   $debug->addHandler('log', new Error\Handlers\Log('/path/to/logfile'));
     *   trigger_error('Uh oh!');
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Error extends Dispatcher
    {
        protected $instanceOf = '\\Phork\\Core\\Error\\Handlers\\HandlerInterface';

        protected $verbose;
        protected $backtrace;

        protected $errors;
        protected $details;
        protected $backtraces;
        protected $handling;


        /**
         * Sets this object as the default error handler.
         *
         * @access public
         * @param boolean $verbose Whether errors should contain file name and line numbers
         * @param boolean $backtrace Whether to store backtrace data
         */
        public function __construct($verbose = false, $backtrace = false)
        {
            $this->verbose = $verbose;
            $this->backtrace = $backtrace;

            $this->errors = new Iterators\Associative();
            $this->details = new Iterators\Associative();
            $this->backtraces = new Iterators\Associative();

            set_error_handler(array($this, 'handle'));
            $this->handling = true;
        }
        

        /**
         * Restores the error handler to the previously registered
         * handler.
         *
         * @access public
         * @return void
         */
        public function __destruct()
        {
            if ($this->handling) {
                restore_error_handler();
                $this->handling = false;
            }
        }
        

        /**
         * Handles the error information. The user errors triggered by
         * trigger_error() are automatically handled regardless of the
         * error reporting. The following error levels can't be handled:
         * E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING
         * E_COMPILE_ERROR, E_COMPILE_WARNING.
         *
         * @access public
         * @param integer $level The error level
         * @param string $error The error message
         * @param string $file The file containing the error
         * @param integer $line The line number of the error
         * @return boolean True so as to not execute PHP's internal handler
         */
        public function handle($level, $error, $file, $line)
        {
            $userError = ($level == E_USER_NOTICE || $level == E_USER_WARNING || $level == E_USER_ERROR);
            if ($userError || $level & error_reporting()) {
                switch ($level) {
                    case E_STRICT:
                    case E_USER_NOTICE:
                    case E_NOTICE:
                        $type = 'Notice';
                        break;

                    case E_USER_WARNING:
                    case E_COMPILE_WARNING:
                    case E_CORE_WARNING:
                    case E_WARNING:
                        $type = 'Warning';
                        break;

                    case E_PARSE:
                    case E_USER_ERROR:
                    case E_COMPILE_ERROR:
                    case E_CORE_ERROR:
                    case E_ERROR:
                    default:
                        $type = 'Error';
                        break;
                }

                $error = $this->verbose ? sprintf('%s: %s in %s on line %d', $type, $error, $file, $line) : $error;
                $id = $this->errors->append($error);
                
                $this->details->append(array($id, array($level, $error, $file, $line)));
                $this->backtrace && $this->backtraces->append(array($id, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

                $args = func_get_args();
                array_unshift($args, $type);
                $this->__call('handle', $args);
            }

            return true;
        }
        

        /**
         * Clears out all the errors data.
         *
         * @access public
         * @return void
         */
        public function clear()
        {
            $this->errors->clear();
            $this->details->clear();
            $this->backtraces->clear();
        }
        

        /**
         * Returns the error iterator object.
         *
         * @access public
         * @return object The iterator object of errors
         */
        public function getErrors()
        {
            return $this->errors;
        }
        

        /**
         * Returns the details iterator object.
         *
         * @access public
         * @return object The iterator object of details
         */
        public function getDetails()
        {
            return $this->details;
        }
        

        /**
         * Returns the backtrace iterator object.
         *
         * @access public
         * @return object The iterator object of backtraces
         */
        public function getBacktraces()
        {
            return $this->backtraces;
        }
        

        /**
         * Returns the verbosity flag.
         *
         * @access public
         * @return boolean The verbose flag
         */
        public function getVerbose()
        {
            return $this->verbose;
        }
    }
