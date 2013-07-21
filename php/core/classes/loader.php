<?php
    namespace Phork\Core;

    /**
     * Loads in the various application and core files with special methods
     * for certain file types. Includes the option to define one or more
     * path stacks which will load a stack of files (eg. core then app).
     * This is a singleton.
     *
     * <code>
     *   //define a stack that first tries to load core file and then app files
     *   $this->addStack('default', array(
     *     'core' => CORE_PATH,
     *     'app' => APP_PATH
     *   ));
     *
     *   //load the Foo class using the default stack and return a new Foo object
     *   $this->loadStack('default', 'foo',
     *     function($result, $type) {
     *       $class = sprintf('\\Phork\\%s\\Foo', ucfirst($type));
     *       return new $class();
     *     }
     *   );
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Loader extends Singleton
    {
        protected $map = array();
        protected $stacks = array();
        protected $extension = '.php';

        
        /**
         * Set this object as one of the autoloaders to use for missing classes.
         * The constructor can't be public for a singleton.
         *
         * @access protected
         */
        protected function __construct()
        {
            spl_autoload_register(array($this, 'loadClass'));
        }


        /**
         * Remove this from being used as an autoloader.
         *
         * @access public
         */
        public function __destruct()
        {
            spl_autoload_unregister(array($this, 'loadClass'));
        }


        /**
         * Loads a class from the core directory.
         *
         * @access public
         * @param string $path The path to the file relative to the CORE_PATH directory
         * @param string $type The type of file (eg. config, classes, classes/foo)
         * @return mixed The return value from the require
         */
        public function loadCore($path, $type = 'classes')
        {
            return $this->loadFile(CORE_PATH.$type.DIRECTORY_SEPARATOR.$path.$this->extension);
        }


        /**
         * Loads a class from the app directory.
         *
         * @access public
         * @param string $path The path to the file relative to the APP_PATH directory
         * @param string $type The type of file (eg. config, classes, classes/foo)
         * @return mixed The return value from the require
         */
        public function loadApp($path, $type = 'classes')
        {
            return $this->loadFile(APP_PATH.$type.DIRECTORY_SEPARATOR.$path.$this->extension);
        }


        /**
         * Loads a file and returns the result. If the loaded file doesn't contain
         * a return statement it will return 1 if the file was loaded successfully.
         *
         * @access public
         * @param string $fullpath The absolute path to the file
         * @return mixed The return value from the require
         */
        public function loadFile($fullpath)
        {
            return require $this->validateFile($fullpath);
        }
        

        /**
         * Loads a class by class name without knowing the path. First checks
         * the class map array and if no value is found this will try to parse
         * out a file path based on the namespace and class. Can be used with
         * spl_autoload_register().
         *
         * @access public
         * @param string $class The name of the class to load
         * @return boolean True on success
         */
        public function loadClass($class)
        {
            if (!(array_key_exists($class, $this->map) && $fullpath = $this->map[$class])) {
                if ($namespaces = explode('\\', preg_replace('/^\\\/', '', $class))) {
                    if (($vendor = array_shift($namespaces) == 'Phork') && $type = strtoupper(array_shift($namespaces))) {
                        if (defined($pathvar = strtoupper($type).'_PATH') && $root = constant($pathvar)) {
                            switch ($type) {
                                case 'PKG':
                                    $package = strtolower(array_shift($namespaces));
                                    if ($namespaces) {
                                        $fullpath = $root.$package.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_map('strtolower', $namespaces)).$this->extension;
                                    } else {
                                        $fullpath = $root.$package.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.$package.$this->extension;
                                    }
                                    break;

                                default:
                                    $fullpath = $root.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_map('strtolower', $namespaces)).$this->extension;
                                    break;
                            }
                        }
                    }
                }
            }
            
            if (!empty($fullpath) && $fullpath = $this->isFile($fullpath)) {
                return (require $fullpath) && class_exists($class, false);
            } else {
                throw new \PhorkException(sprintf('Unable to load class %s', $class));
            }
        }
        

        /**
         * Loads a stack of files from the predefined stack list and if a
         * callback function exists it will either run all the callbacks for
         * successfully loaded items if the $runall flag is true, or it will
         * just run the callback for the last successfully loaded item if
         * the $runall flag is false
         *
         * @access public
         * @param string $path The path to the class relative to the $roots directories
         * @param array $file The filename (excluding the extension) of the stack
         * @param closure $callback The function to run for the loaded file(s)
         * @param string $folder The relative path to the folder (eg. config, classes, classes/foo)
         * @param boolean $runall Whether to run the callbacks for all the loaded files or just the latest
         * @return mixed The result(s) from the called callback(s)
         */
        public function loadStack($name, $file, $callback = null, $folder = 'classes', $runall = false, $ext = null)
        {
            if (!array_key_exists($name, $this->stacks)) {
                throw new \PhorkException(sprintf('Unable to load from non-existent stack %s', $name));
            }

            foreach ($this->stacks[$name] as $type=>$root) {
                if ($fullpath = $this->isFile($root.$folder.DIRECTORY_SEPARATOR.$file.($ext ?: $this->extension))) {
                    $result = $this->loadFile($fullpath);

                    if ($runall) {
                        if (is_callable($callback)) {
                            $results[$root] = $callback($result, $type);
                        }
                    } else {
                        $run = array(
                            'callback' => $callback,
                            'result' => $result,
                            'type' => $type
                        );
                    }
                }
            }

            if (isset($run) && is_callable($run['callback'])) {
                $results = $run['callback']($run['result'], $run['type']);
            }

            return isset($results) ? $results : null;
        }
        

        /**
         * Adds a new stack to the list of available stacks. If the overwrite
         * flag is false this will throw an exception instead of overwriting
         * the existing stack.
         *
         * @access public
         * @param string $name The name of the stack
         * @param array $stack The stack keyed by the full path with the path type (eg. Core) as the value
         * @param boolean $overwrite Whether an existing stack can be overwritten
         * @return void
         */
        public function addStack($name, array $stack, $overwrite = false)
        {
            if (!($exists = array_key_exists($name, $this->stacks)) || $overwrite) {
                $this->stacks[$name] = $stack;
            } elseif ($exists) {
                throw new \PhorkException(sprintf('The %s stack already exists and cannot be overwritten', $name));
            }
        }
        

        /**
         * Removes a stack from the available stacks and returns it.
         *
         * @param string $name The name of the stack
         * @param boolean $warn Whether to throw an exception if removing a non-existent stack
         * @return array The removed stack
         */
        public function removeStack($name, $warn = true)
        {
            if (array_key_exists($name, $this->stacks)) {
                $return = $this->stacks[$name];
                unset($this->stacks[$name]);
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Unable to remove non-existent stack %s', $name));
            }

            return isset($return) ? $return : null;
        }
        

        /**
         * Validates a file path and optionally checks that it's contained
         * within the restricted directory passed.
         *
         * @access public
         * @param string $fullpath The absolute path to the file
         * @param string $restrict The optional path to restrict the file to
         * @return string The cleaned full path to a valid file
         */
        public function validateFile($fullpath, $restrict = null)
        {
            if (is_file($cleanpath = $this->cleanPath($fullpath, true))) {
                if ($restrict) {
                    if ($restrict = $this->cleanPath($restrict, true)) {
                        if (substr($cleanpath, 0, strlen($restrict)) != $restrict) {
                            throw new \PhorkException(sprintf('The %s file does is not in the restricted directory', $fullpath));
                        }
                    } else {
                        throw new \PhorkException(sprintf('Invalid directory restriction %s', $restrict));
                    }
                }
            } else {
                throw new \PhorkException(sprintf('Invalid file %s', $fullpath));
            }

            return $cleanpath;
        }


        /**
         * A convenience wrapper for the validateFile method that ignores
         * the exceptions and just returns the filepath or false.
         *
         * @access public
         * @param string $fullpath The absolute path to the file
         * @param string $restrict The optional path to restrict the file to
         * @return string The cleaned full path to a valid file or false on failure
         */
        public function isFile($fullpath, $restrict = null)
        {
            try {
                return $this->validateFile($fullpath, $restrict);
            } catch (Exception $exception) {
                return false;
            }
        }


        /**
         * Checks whether a template exists in the path passed.
         *
         * @access public
         * @param string $path The path to the file relative to the view directory
         * @param string $ext The file extension of the template (eg. .phtml)
         * @return string The full path to the file
         */
        public function isTemplate($path, $ext = null)
        {
            return $this->isFile(VIEW_PATH.DIRECTORY_SEPARATOR.$path.($ext ?: $this->extension), VIEW_PATH);
        }


        /**
         * Maps a class name to a file so that loadClass() will be able
         * to load classes in non-standard locations.
         *
         * @access public
         * @param string $class The name of the class
         * @param string $file The full path to the file containing the class
         * @return void
         */
        public function mapClass($class, $file)
        {
            $this->map[$class] = $file;
        }


        /**
         * Replaces the slashed path with the appropriate directory
         * separator and optionally returns the realpath of the file.
         * If the file doesn't exist then realpath will return false.
         *
         * @access protected
         * @param string $path The path name to clean
         * @param boolean $realpath Whether to return the realpath to the file
         * @return string The cleaned path or false on failure
         */
        protected function cleanPath($path, $realpath = false)
        {
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
            return $realpath ? realpath($path) : $path;
        }
    }
