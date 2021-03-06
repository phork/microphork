<?php
    namespace Phork\Core;

    /**
     * The loader is used to include additional files. It can be used to
     * load core, app and package classes, config files, language files and
     * templates. It has special handling for classes in the Phork namespace,
     * as well as the ability to map additional namespaces to custom load
     * functions. It includes the option to define one or more path stacks
     * which will load a stack of files (eg. core then app) with an optional
     * callback function. This is a singleton.
     *
     * <code>
     *   //set up the absolute basic path mapping
     *   $this
     *     ->mapPath('Core', CORE_PATH)
     *     ->mapPath('App', APP_PATH)
     *     ->mapPath('Pkg', PKG_PATH)
     *     ->mapPath('View', VIEW_PATH)
     *   ;
     *
     *   //define a stack that first tries to load core file and then app files
     *   $this->addStack('default', array('Core', 'App'));
     *   
     *   //define a package stack using the mapped App path and a custom package path
     *   $this->addStack('package', array('App', array('Pkg', $pkg)));
     *
     *   //load the Foo class using the default stack and return a new Foo object
     *   $this->loadStack('default', 'Foo',
     *     function ($result, $type) {
     *       $class = sprintf('\\Phork\\%s\\Foo', $type);
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
     * @package \Phork\Core
     */
    class Loader extends Singleton
    {
        protected $autoloader = false;
        protected $classes = array();
        protected $namespaces = array();
        protected $stacks = array();
        protected $paths = array();
        protected $extension = '.php';

        
        /**
         * Sets up the Phork namespace to use this loader. Also sets this
         * object as one of the autoloaders to use for missing classes.
         * The constructor can't be public for a singleton. 
         *
         * PHP 5.4+ binds the closure to $this which will make a circular
         * reference so in that case this rebinds it to a standard object.
         *
         * @access protected
         */
        protected function __construct()
        {
            $extension = $this->extension;
            $paths = &$this->paths;
            
            $closure = function ($class, $unmatched) use ($extension, &$paths) {
                if (($type = array_shift($unmatched)) && array_key_exists($type, $paths) && $path = $paths[$type]) {
                    if ($type == 'Pkg') {
                        $package = array_shift($unmatched);
                        return $path.$package.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $unmatched).$extension;
                    } else {
                        return $path.'classes'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $unmatched).$extension;
                    }
                } else {
                    throw new \PhorkException(sprintf('No path defined for Phork\%s', $type));
                }
            };
            
            $this->mapNamespace('Phork', method_exists($closure, 'bindTo') ? $closure->bindTo(new \StdClass()) : $closure);
        }
        
        /**
         * Removes this class from the autoload stack before destruction.
         *
         * @access public
         */
        public function __destruct()
        {
            $this->autoload(false);
            parent::__destruct();
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
                if (!$this->autoloader) {
                    $this->autoloader = spl_autoload_register(array($this, 'loadClass'));
                }
            } else {
                if ($this->autoloader) {
                    $this->autoloader = !spl_autoload_unregister(array($this, 'loadClass'));
                }
            }
        }
        
        //-----------------------------------------------------------------
        //   mapping methods
        //-----------------------------------------------------------------

        /**
         * Maps a class name to a file so that loadClass() will be able
         * to load classes in non-standard locations.
         *
         * @access public
         * @param string $class The name of the class
         * @param string $file The full path to the file containing the class
         * @return object The instance of the loader object
         */
        public function mapClass($class, $file)
        {
            $this->classes[$class] = $file;
            return $this;
        }
        
        /**
         * Maps a namespace to a class loader so that loadClass() will
         * be able to load classes in non-standard locations.
         *
         * @access public
         * @param string $namespace A full or partial namespace 
         * @param callback $loader The loader callback
         * @return object The instance of the loader object
         */
        public function mapNamespace($namespace, $loader)
        {
            $this->namespaces[$namespace] = $loader;
            return $this;
        }

        /**
         * Maps a path name to the full path for it. At the very least
         * there should be Core, App, Pkg and View. This returns an
         * instance of itself for chaining.
         *
         * @access public
         * @param string $name The name of the path (eg. Core)
         * @param string $path The path
         * @return object The instance of the loader object
         */
        public function mapPath($name, $path)
        {
            $this->paths[$name] = $path;
            return $this;
        }
        
        /**
         * Returns a path from the array of paths.
         *
         * @access public
         * @param string $name The name of the path to get
         * @return string The path
         */
        public function getPath($name)
        {
            if (array_key_exists($name, $this->paths)) {
                return $this->paths[$name];
            } else {
                throw new \PhorkException(sprintf('Invalid path name: %s', $name));
            }
        }
        
        //-----------------------------------------------------------------
        //   loader methods
        //-----------------------------------------------------------------
        
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
         * and class. Can be used with spl_autoload_register() however if it
         * is it must not throw an exception.
         *
         * @access public
         * @param string $class The name of the class (or interface) to load
         * @param boolean $warn Whether to throw an exception if the class isn't found
         * @return boolean True on success
         */
        public function loadClass($class, $warn = false)
        {
            if (!class_exists($class, false) && !interface_exists($class, false)) {
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
                } elseif ($warn) {
                    throw new \PhorkException(sprintf('Unable to load class %s', $class));
                }
            } else {
                return true;
            }
        }
        
        //-----------------------------------------------------------------
        //   stack methods
        //-----------------------------------------------------------------
        
        /**
         * Loads a stack of files from the predefined stack list and if a
         * callback function exists it will either run all the callbacks for
         * successfully loaded items if the $runall flag is true, or it will
         * just run the callback for the last successfully loaded item if
         * the $runall flag is false. This won't fail if nothing was loaded.
         *
         * @access public
         * @param string $name The name of the stack to load the files from
         * @param array $file The file name (excluding the extension) of the file(s) to load
         * @param callable $callback The closure, function name or method called for successful loads
         * @param string $folder The file path relative to the stack paths (eg. config, classes, classes/Foo)
         * @param boolean $runall Whether to run the callbacks for all the loaded files or just the latest
         * @param string $ext The extension of the file (eg. .php)
         * @param boolean $once Whether to use require_once instead of require
         * @return mixed The result(s) from the called callback(s)
         */
        public function loadStack($name, $file, $callback = null, $folder = 'classes', $runall = false, $ext = null, $once = true)
        {
            foreach ($this->listStack($name, $file, $folder, $ext) as $type => $fullpath) {
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

            if (isset($run)) {
                $results = call_user_func_array($callback, $run);
            }

            return isset($results) ? $results : null;
        }
        
        /**
         * Returns the full paths to all the files found in the stack.
         *
         * @access public
         * @param string $name The name of the stack to list the files from
         * @param array $file The file name (excluding the extension) of the file(s) to list
         * @param string $folder The file path relative to the stack paths (eg. config, classes, classes/Foo)
         * @param string $ext The extension of the file (eg. .php)
         * @return array The array of paths for the files that exist
         */
        public function listStack($name, $file, $folder = 'classes', $ext = null)
        {
            $results = array();
            
            foreach ($this->getStack($name) as $item) {
                if (is_array($item)) {
                    list($type, $path) = $item;
                } else {
                    $type = $item;
                    $path = $this->getPath($type);
                }
                
                if ($fullpath = $this->isFile($path.$folder.DIRECTORY_SEPARATOR.$file.($ext ?: $this->extension))) {
                    $results[$type] = $fullpath;
                }
            }
            
            return $results;
        }
        
        /**
         * Adds a new stack to the list of available stacks. If the overwrite
         * flag is false this will throw an exception instead of overwriting
         * the existing stack.
         *
         * @access public
         * @param string $name The name of the stack
         * @param array $stack The array of stack paths (eg. 'Core' or array('Pkg' => '/path/to/pkg'))
         * @param boolean $overwrite Whether an existing stack can be overwritten
         * @return object The instance of the loader object
         */
        public function addStack($name, array $stack, $overwrite = false)
        {
            if (!($exists = array_key_exists($name, $this->stacks)) || $overwrite) {
                $this->stacks[$name] = $stack;
            } elseif ($exists) {
                throw new \PhorkException(sprintf('The %s stack already exists and cannot be overwritten', $name));
            }

            return $this;
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
        
        //-----------------------------------------------------------------
        //   validation methods
        //-----------------------------------------------------------------
        
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
                            throw new \PhorkException(sprintf('The %s file is outside the allowed directory', $fullpath));
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
            return $this->isFile($this->getPath('View').$path.($ext ?: $this->extension), $this->getPath('View'));
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
