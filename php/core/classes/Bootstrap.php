<?php
    namespace Phork\Core;

    /**
     * The bootstrap includes the config files and the global classes
     * and initializes the application. Several public methods in here
     * return the object itself to allow for daisy chaining calls.
     * This is a singleton.
     *
     * In a nutshell this will map the first segment of the URL to an
     * app controller of the same name and then run its run() method.
     * The default behavior of the controller is to use the second URL
     * segment to determine the controller method. So /foo/bar/baz/
     * would map to the \Phork\App\Controllers\Foo::displayBar('baz').
     *
     * <code>
     *   Phork::instance()
     *        ->register('loader', \Phork\Core\Loader::instance(true))
     *        ->init(PHK_ENV)
     *        ->run()
     *        ->shutdown()
     *   ;
     * </code>
     *
     * <code>
     *   //call a registry object from within this object
     *   $this->config->get('foobar');
     *
     *   //call a registry object from outside this object
     *   Phork::instance()->config->get('foobar');
     *   Phork::config()->get('foobar');
     *
     *   //load and initialize the auth package
     *   $class = Phork::instance()->initPackage('Auth');
     *   $auth = new $class();
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Bootstrap extends Singleton
    {
        protected $registry = array();
        protected $initialized = false;
        protected $autoloader = true;

        const LOAD_STACK = 'app';

        
        /**
         * Sets up empty registry keys for the default base objects.
         * The constructor can't be public for a singleton.
         *
         * @access protected
         */
        protected function __construct()
        {
            $this->registry = array_fill_keys(array(
                'loader',
                'config',
                'event',
                'error',
                'language',
                'router',
                'output',
                'controller'
            ), null);
        }
        
        /**
         * Don't let the bootstrap be dereferenced.
         *
         * @access public
         * @static
         */
        public static function dereference()
        {
            throw new \PhorkException('The bootstrap cannot be dereferenced');
        }

        /**
         * Dispatches to the other initialization methods. These can be
         * added to or overridden in the app bootstrap class. If any of
         * the base objects have already been registered they won't be
         * overwritten here. Also sets up the default loader stack paths
         * and sets up the loader to be used as an autoloader.
         *
         * @access public
         * @param string $env The environment to initialize (eg. prod, stage, dev)
         * @return object The instance of the bootstrap object
         */
        public function init($env)
        {
            try {
                $this->loader->addStack(static::LOAD_STACK, array('Core', 'App'));
            } catch (Exception $exception) {
                //the stack has already been defined; not a problem
            }
            
            $this->autoloader && $this->loader->autoload(true);

            empty($this->registry['config'])   && $this->initConfig($env);
            empty($this->registry['event'])    && $this->initEvent();
            empty($this->registry['error'])    && $this->initError();
            empty($this->registry['language']) && $this->initLanguage();
            empty($this->registry['router'])   && $this->initRouter();
            empty($this->registry['output'])   && $this->initOutput();
            
            $this->autoloader && $this->event->listen('shutdown.run.before', array($this->loader, 'autoload'), array(false));
            
            $this->initialized = true;
            return $this;
        }

        /**
         * Runs the application by determining the controller and then
         * dispatching to it. The bootstrap.run.before event can be used
         * to turn on output buffering and bootstrap.run.after can be
         * used to flush the buffer.
         *
         * $this->event->listen('bootstrap.run.before', array(\Phork::output(), 'buffer'));
         * $this->event->listen('bootstrap.run.after', array(\Phork::output(), 'flush'));
         *
         * @access public
         * @return object The instance of the bootstrap object
         */
        public function run()
        {
            $this->event->trigger('bootstrap.run.before');

            $segment = ($this->router->getSegment(0) ?: $this->config->router->defaults->controller);
            $class = $this->loader->loadStack(static::LOAD_STACK, ucfirst($segment), (
                function ($result, $type) use ($segment) {
                    $class = sprintf('\\Phork\\%s\\Controllers\\%s', $type, ucfirst($segment));
                    return $class;
                }
            ), 'classes/Controllers');

            if ($class) {
                $this->register('controller', $controller = new $class());
                $controller->run();
            } else {
                $this->fatal(404);
            }

            $this->event->trigger('bootstrap.run.after');

            return $this;
        }

        /**
         * Unsets most of the registry objects. If any of these are referenced
         * elsewhere they will only be dereferenced here and won't actually be
         * destroyed until later. The order of destruction is important and
         * the error handler is excluded because it may still be used later.
         *
         * @access public
         * @return void
         */
        public function shutdown()
        {
            $this->event->trigger('shutdown.run.before');
            
            unset(
                $this->registry['controller'],
                $this->registry['output'],
                $this->registry['loader'],
                $this->registry['router'],
                $this->registry['event'],
                $this->registry['config'],
                $this->registry['language']
            );
        }

        /**
         * Displays a fatal error using one of the error templates if found,
         * or a generic template if not.
         *
         * @access public
         * @param integer $statusCode The HTTP status code
         * @param string $statusString A string to use instead of the default code string
         * @param string $exception An optional exception which can be used for verbose output
         * @return void
         */
        public function fatal($statusCode, $statusString = null, $exception = null)
        {
            if (!$this->loader->isTemplate($template = 'errors/'.$statusCode)) {
                $template = 'errors/catchall';
            }

            $this->output
                ->clear()
                ->setStatusCode($statusCode)
                ->addTemplate($template, array(
                    'statusCode' => $statusCode,
                    'statusString' => $statusString ?: $this->output->getStatusCode($statusCode),
                    'exception' => $exception
                ))
            ;
        }

        /**
         * Adds an object to the registry and returns an instance of itself
         * to allow for chaining.
         *
         * @access public
         * @param string $name The name of the object to register
         * @param object $value The object to register
         * @param boolean $create Whether to create a new object if one doesn't exist
         * @return object The instance of the bootstrap object
         */
        public function register($name, $value, $create = false)
        {
            if (($create || array_key_exists($name, $this->registry)) && is_object($value)) {
                $this->registry[$name] = $value;
            } else {
                throw new \PhorkException(sprintf('Invalid registration (%s)', $name));
            }

            return $this;
        }
        
        /**
         * Removes an object from the registry and returns it.
         *
         * @access public
         * @param string $name The name of the object to deregister
         * @return object The object that was removed
         */
        public function deregister($name)
        {
            if (array_key_exists($name, $this->registry)) {
                $object = $this->registry[$name];
                unset($this->registry[$name]);
            } else {
                throw new \PhorkException(sprintf('Invalid deregistration (%s)', $name));
            }
            
            return $object;
        }

        //-----------------------------------------------------------------
        //   initialization methods
        //-----------------------------------------------------------------

        /**
         * Loads the global and environmental configuration files.
         *
         * @access protected
         * @param string $env The environment to initialize (eg. prod, stage, dev)
         * @return void
         */
        protected function initConfig($env)
        {
            $this->register('config', $this->loader->loadStack(static::LOAD_STACK, 'Config',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Config', $type);
                    return new $class();
                }
            ));

            $this->config->load('global');
            $this->config->load('environments'.DIRECTORY_SEPARATOR.$env);
        }
        
        /**
         * Loads and initializes the event class. The event class is a
         * singleton that's immediately dereferenced because it's stored
         * here instead.
         *
         * @access protected
         * @return void
         */
        protected function initEvent()
        {
            $this->register('event', $this->loader->loadStack(static::LOAD_STACK, 'Event',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Event', $type);
                    return $class::instance(true);
                }
            ));
        }

        /**
         * Loads and initializes the error dispatcher and adds each of
         * the configured handlers.
         *
         * @access protected
         * @return void
         */
        protected function initError()
        {
            $config = $this->config->get('error');

            $this->register('error', $this->loader->loadStack(static::LOAD_STACK, 'Error',
                function ($result, $type) use ($config) {
                    $class = sprintf('\\Phork\\%s\\Error', $type);
                    return new $class($config->verbose, $config->backtrace);
                }
            ));

            if ($config->handlers && $handlers = $config->handlers->export()) {
                $this->error->init($handlers);
            }
        }

        /**
         * Loads and initializes the language class and sets it up to
         * handle any translations.
         *
         * @access protected
         * @return void
         */
        protected function initLanguage()
        {
            $config = $this->config->get('language');

            $this->register('language', $this->loader->loadStack(static::LOAD_STACK, 'Language',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Language', $type);
                    return new $class();
                }
            ));

            if ($config) {
                foreach ($stack = $this->loader->getStack(static::LOAD_STACK) as $type => $root) {
                    $stack[$type] = $root.'lang'.DIRECTORY_SEPARATOR;
                }
                
                $this->language->setFilePaths($stack);
                $this->language->setCachePath($config->cachepath);
                $this->language->setLanguage($config->language);
            }
        }

        /**
         * Routes the application to the appropriate controller based on
         * the URL or the CLI args. The router is not a singleton because
         * it can be useful to store or parse routes other than the current
         * one.
         *
         * @access protected
         * @return void
         */
        protected function initRouter()
        {
            $config = $this->config->get('router');

            $this->register('router', $this->loader->loadStack(static::LOAD_STACK, 'Router',
                function ($result, $type) use ($config) {
                    $class = sprintf('\\Phork\\%s\\Router', $type);
                    return new $class($config->urls->base, $config->defaults->endslash, $config->defaults->mixedpost);
                }
            ));

            $this->router->setRoutes($config->routes ? $config->routes->export() : null);
            $this->router->detectCli();
            $this->router->init();
        }

        /**
         * Loads and initializes the output class. The output class is
         * a singleton that's immediately dereferenced because it's stored
         * here instead.
         *
         * @access protected
         * @return void
         */
        protected function initOutput()
        {
            $this->register('output', $this->loader->loadStack(static::LOAD_STACK, 'Output',
                function ($result, $type) {
                    $class = sprintf('\\Phork\\%s\\Output', $type);
                    return $class::instance(true);
                }
            ));
            
            $this->event->listen('shutdown.run.before', array($this->output, 'flush'));
        }

        /**
         * Loads and initializes a package and returns the callback result.
         * This creates a new package stack, loads the config files, then the
         * language files and finally the class stack. If no callback is
         * defined then this will return the name of the latest class in the
         * stack.
         *
         * @access public
         * @param string $pkg The name of the package to load
         * @param callable $callback A closure, function name or method to pass to the load stack method
         * @param boolean $unstack Whether to remove the load stack when finished
         * @return mixed The result of the callback
         */
        public function initPackage($pkg, $callback = null, $unstack = true)
        {
            $path = $this->loader->getPath('Pkg').$pkg.DIRECTORY_SEPARATOR;
            
            try {
                $this->loader->addStack($stack = $pkg.'stack', array(array('Pkg', $path), 'App'));
            } catch (Exception $exception) {
                //the stack has already been defined; not a problem
            }
            
            if (is_null($callback)) {
                $callback = function ($result, $type) use ($pkg) {
                    $class = sprintf('\\Phork\\%s\\%s', $type, $pkg);
                    if ($type == 'Pkg') {
                        $class .= '\\'.$pkg;
                    }
                    return $class;
                };
            }

            $this->config->load(strtolower($pkg), $stack);
            $this->language->addFilePath($path.'lang'.DIRECTORY_SEPARATOR);
            $result = $this->loader->loadStack($stack, $pkg, $callback);
            $unstack && $this->loader->removeStack($stack);

            return $result;
        }

        //-----------------------------------------------------------------
        //   magic methods
        //-----------------------------------------------------------------

        /**
         * Called when isset is called on the bootstrap. This is used to
         * check if a registry object exists.
         *
         * @access public
         * @param string $name The name of the variable called
         * @return boolean True if the registry object exists
         */
        public function __isset($name)
        {
            return !empty($this->registry[$name]);
        }

        /**
         * Called when an unknown or un-public variable is called. This is
         * used as a way to pass through calls to the registry objects.
         *
         * @access public
         * @param string $name The name of the registry object called
         * @return object The registered object
         */
        public function __get($name)
        {
            if (array_key_exists($name, $this->registry) && is_object($this->registry[$name])) {
                return $this->registry[$name];
            } else {
                throw new \PhorkException(sprintf('Invalid registry object: %s', $name));
            }
        }

        /**
         * Called when an unknown static method is called. This is used as
         * a way to pass through calls to the registry objects.
         *
         * @access public
         * @param string $name The name of the method called
         * @param array $args The arguments passed to the method
         * @return object The object property
         */
        public static function __callStatic($name, $args)
        {
            $registry = self::instance()->registry;
            if (array_key_exists($name, $registry) && is_object($registry[$name])) {
                return $registry[$name];
            } else {
                throw new \PhorkException(sprintf('Invalid registry object: %s', $name));
            }
        }
    }
