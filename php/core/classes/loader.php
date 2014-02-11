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
     *     function ($result, $type) {
     *       $class = sprintf('\\Phork\\%s\\Foo', ucfirst($type));
     *       return new $class();
     *     }
     *   );
     *
     *   //load a set of global config files and fail if none were found
     *   if (!$this->loadStack('default', 'global', function () { return true; }, 'config') {
     *     throw new \PhorkException('No global config files found');
     *   }
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Loader extends Singleton
    {
        protected $classes = array();
        protected $namespaces = array();
        protected $stacks = array();
        protected $extension = '.php';

        
        /**
         * Sets up the Phork namespace to use this loader. Also sets this 
         * object as one of the autoloaders to use for missing classes.
         * The constructor can't be public for a singleton.
         *
         * @access protected
         */
        protected function __construct()
        {
            $extension = $this->extension;
            $this->mapNamespace('Phork', function ($class, $unmatched) use ($extension) {
                if (($type = array_shift($unmatched)) && defined($pathvar = strtoupper($type).'_PATH') && ($root = constant($pathvar))) {
                    if ($type == 'Pkg') {
                        $package = strtolower(array_shift($unmatched));
                        return $root.$package.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_map('strtolower', $unmatched)).$extension;
                    } else {
                        return $root.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_map('strtolower', $unmatched)).$extension;
                    }
                } else {
                    throw new \PhorkException(sprintf('No path defined for Phork\%s', $type));
                }
            });
        }


        /**
         * Adds or remove this class from the autoload stack based on the
         * active flag passed.
         *
         * @access public
         * @param boolean $active Whether to set as an autoloader
         * @return void 
         */
        public function autoload($active)
        {
            if ($active) {
                spl_autoload_register(array($this, 'loadClass'));
            } else {
                spl_autoload_unregister(array($this, 'loadClass'));
            }
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
            $this->classes[$class] = $file;
        }
        
        
        /**
         * Maps a namespace to a class loader so that loadClass() will
         * be able to load classes in non-standard locations.
         *
         * @access public
         * @param string $namespace A full or partial namespace 
         * @param callback $loader The loader callback
         * @return void
         */
        public function mapNamespace($namespace, $loader)
        {
            $this->namespaces[$namespace] = $loader;
        }


        /**
         * Loads a class from the core directory.
         *
         * @access public
         * @param string $path The path to the file relative to the CORE_PATH directory
         * @param string $type The type of file (eg. config, classes, classes/foo)
         * @param boolean $once Whether to use require_once instead of require
         * @return mixed The return value from the require
         */
        public function loadCore($path, $type = 'classes', $once = true)
        {
            return $this->loadFile(CORE_PATH.$type.DIRECTORY_SEPARATOR.$path.$this->extension, $once);
        }


        /**
         * Loads a class from the app directory.
         *
         * @access public
         * @param string $path The path to the file relative to the APP_PATH directory
         * @param string $type The type of file (eg. config, classes, classes/foo)
         * @param boolean $once Whether to use require_once instead of require
         * @return mixed The return value from the require
         */
        public function loadApp($path, $type = 'classes', $once = true)
        {
            return $this->loadFile(APP_PATH.$type.DIRECTORY_SEPARATOR.$path.$this->extension, $once);
        }


        /**
         * Loads a file and returns the result. If the loaded file doesn't contain
         * a return statement it will return 1 if the file was loaded successfully.
         *
         * @access public
         * @param string $fullpath The absolute path to the file
         * @param boolean $once Whether to use require_once instead of require
         * @return mixed The return value from the require
         */
        public function loadFile($fullpath, $once = true)
        {
            if ($once) {
                return require_once $this->validateFile($fullpath);
            } else {
                return require $this->validateFile($fullpath);
            }
        }
        

        /**
         * Loads a class by class name without knowing the path. First checks
         * the class map array and if no value is found this will check the 
         * namespace map to try to parse out a file path based on the namespace
         * and class. Can be used with spl_autoload_register().
         *
         * @access public
         * @param string $class The name of the class (or interface) to load
         * @return boolean True on success
         */
        public function loadClass($class)
        {
            if (!(array_key_exists($class, $this->classes) && $fullpath = $this->classes[$class])) {
                if ($pieces = explode('\\', preg_replace('/^\\\/', '', $class))) {
                    $popped = array();
                    do {
                        if (array_push($popped, array_pop($pieces)) && !empty($this->namespaces[$joined = implode('\\', $pieces)])) {
                            $fullpath = call_user_func_array($this->namespaces[$joined], array($class, array_reverse($popped)));
                            break;
                        }
                    } while ($pieces);
                }
            }
            
            if (!empty($fullpath) && $fullpath = $this->isFile($fullpath)) {
                return (require $fullpath) && (class_exists($class, false) || interface_exists($class, false));
            } else {
                throw new \PhorkException(sprintf('Unable to load class %s', $class));
            }
        }
        

        /**
         * Loads a stack of files from the predefined stack list and if a
         * callback function exists it will either run all the callbacks for
         * successfully loaded items if the $runall flag is true, or it will
         * just run the callback for the last successfully loaded item if
         * the $runall flag is false. This won't fail if nothing was loaded.
         *
         * @access public
         * @param string $path The path to the class relative to the $roots directories
         * @param array $file The filename (excluding the extension) of the stack
         * @param callable $callback The closure, function name or method called for successful loads
         * @param string $folder The relative path to the folder (eg. config, classes, classes/foo)
         * @param boolean $runall Whether to run the callbacks for all the loaded files or just the latest
         * @param string $ext The file extension of the file (eg. .php)
         * @param boolean $once Whether to use require_once instead of require
         * @return mixed The result(s) from the called callback(s)
         */
        public function loadStack($name, $file, $callback = null, $folder = 'classes', $runall = false, $ext = null, $once = true)
        {
            foreach ($this->getStack($name) as $type => $root) {
                if ($fullpath = $this->isFile($root.$folder.DIRECTORY_SEPARATOR.$file.($ext ?: $this->extension))) {
                    $result = $this->loadFile($fullpath, $once);

                    if ($callback) {
                        if ($runall) {
                            $results[$type] = call_user_func_array($callback, array($result, $type));
                        } else {
                            $run = array($result, $type);
                        }
                    } else {
                        $results[$type] = $result;
                    }
                }
            }

            if (isset($run)) {
                $results = call_user_func_array($callback, $run);
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
         * Gets a stack if it's been registered.
         *
         * @param string $name The name of the stack
         * @param boolean $warn Whether to throw an exception if getting a non-existent stack
         * @return array The stack
         */
        public function getStack($name, $warn = true)
        {
            if (array_key_exists($name, $this->stacks)) {
                return $this->stacks[$name];
            } elseif ($warn) {
                throw new \PhorkException(sprintf('Unable to get non-existent stack %s', $name));
            }
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
            if ($realpath) {
                return realpath($path);
            } else {
                return str_replace('/', DIRECTORY_SEPARATOR, $path);
            }
        }
    }
