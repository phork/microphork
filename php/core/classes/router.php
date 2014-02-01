<?php
    namespace Phork\Core;

    /**
     * Parses the URL or the CLI args, factors in the custom routing, and
     * splits either the routed URL or the relative URL into segments and
     * filters. The base URL is the front controller path relative to the
     * document root, and including the filename when not using mod rewrite
     * (eg. /admin or index.php). The URL and request data can either be
     * determined automatically or passed as arguments to the init() method.
     *
     * The detectCli() method must be called before init() if there's a
     * chance that the script was accessed via the CLI.
     *
     * When using the CLI the first argument should be the request method,
     * the second argument should be the URL and the third argument should
     * be the variables (eg. php index.php get api/foo.xml "bar=123&baz=456")
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Router
    {
        const FILTER_DELIMITER = '=';

        protected $initialized;
        protected $cli;

        protected $method;
        protected $baseUrl;
        protected $relativeUrl;
        protected $routedUrl;
        protected $segments;
        protected $filters;
        protected $variables;
        protected $rawData;
        protected $extension;
        protected $mixedPost;

        protected $routes;
        protected $endSlash;


        /**
         * Sets up the base URL. The rest of the set up is handled by
         * the init() method.
         *
         * @access public
         * @param string $baseUrl The base path of the system relative to the doc root
         * @param string $endSlash Whether to force the URL to end with a slash
         * @param boolean $mixedPost Whether POST data should also include the GET vars
         */
        public function __construct($baseUrl, $endSlash = false, $mixedPost = false)
        {
            $this->baseUrl = $baseUrl;
            $this->endSlash = $endSlash;
            $this->mixedPost = $mixedPost;
        }


        /**
         * Initializes the URL data. Loads the current URL, routes as
         * necessary, and parses the URL into segments and filters.
         *
         * @access public
         * @param string $method The request method (GET, POST, PUT, DELETE, HEAD)
         * @param string $relativeUrl The URL of the request relative to the base URL
         * @param array $variables Any request variables (ie. to override $_GET)
         * @return void
         */
        public function init($method = null, $relativeUrl = null, $variables = null)
        {
            $this->initialized = false;
            $this->routedUrl = null;

            if ($method !== null) {
                $this->method = strtolower($method);
            } elseif ($this->cli) {
                $this->method = $GLOBALS['argc'] > 1 ? strtolower($GLOBALS['argv'][1]) : 'get';
            } else {
                $this->method = strtolower($_SERVER['REQUEST_METHOD']);
            }

            if ($relativeUrl !== null) {
                $this->relativeUrl = $relativeUrl;
            } elseif (!$this->relativeUrl) {
                $this->detectUrl();
            }

            if ($variables !== null) {
                $this->variables = $variables;
            } else {
                $this->detectVariables();
            }

            $this->routeUrl();
            $this->parseUrl();
            $this->slashUrl();

            $this->initialized = true;
        }


        /**
         * Makes adjustments to account for the URL using a query string.
         * This can be in either the format /index.php?/path/to/page/ if
         * using mod rewrite or /index.php?url=/path/to/page/ if not using
         * mod rewrite. When using the first format no variable should be
         * passed. When using the second format the name of the variable
         * containing the URL should be passed (eg. url). This resets the
         * $_GET array and removes any effect the URL may have had on it.
         * It works with additional non-URL GET data as well as URLs with
         * equals sign filters. For example the query string
         * index.php?/path/to/filter=1/page/ will not end up with a variable
         * named /path/to/filter.
         *
         * This should be called manually before init() if it's needed.
         *
         * @access public
         * @param string $variable The variable name, if the URL isn't the full query string
         * @return void
         */
        public function useQueryString($variable = null)
        {
            $queryString = '';

            if (!empty($_SERVER['QUERY_STRING'])) {
                if ($variable) {
                    if (preg_match('/('.$variable.'=([^&]*))?&?(.*)/', $_SERVER['QUERY_STRING'], $matches)) {
                        if (!empty($matches[2])) {
                            $relativeUrl = $matches[2];
                        }
                        if (!empty($matches[3])) {
                            $queryString = $matches[3];
                        }
                    }
                } else {
                    if (preg_match('/([^\?&]*)[\?&](.*)/', $_SERVER['QUERY_STRING'], $matches)) {
                        list(, $relativeUrl, $queryString) = $matches;
                    } else {
                        $relativeUrl = $_SERVER['QUERY_STRING'];
                    }
                }
            }

            $this->relativeUrl = $relativeUrl;
            parse_str($queryString, $_GET);
        }


        /**
         * Detects whether the script is being accessed via the PHP CLI.
         *
         * @access public
         * @return void
         */
        public function detectCli()
        {
            $this->cli = (php_sapi_name() === 'cli');
        }


        /**
         * Checks for a PHP CLI script first, followed by the path info, and
         * then sets up the URL. The URL does not include the base path.
         *
         * @access public
         * @return void
         */
        protected function detectUrl()
        {
            if ($this->cli) {
                $relativeUrl = $GLOBALS['argc'] > 2 ? $GLOBALS['argv'][2] : '';
            } elseif (!empty($_SERVER['PATH_INFO'])) {
                $relativeUrl = str_replace($this->baseUrl, '', $_SERVER['PATH_INFO']);
            } elseif (!empty($_SERVER['REQUEST_URI'])) {
                $relativeUrl = str_replace($this->baseUrl, '', $_SERVER['REQUEST_URI']);
            } else {
                $relativeUrl = '/';
            }

            list($this->relativeUrl,) = explode('?', $this->cleanUrl($relativeUrl));
        }


        /**
         * Sets the request variables based on the request method.
         *
         * @access protected
         * @return void
         */
        protected function detectVariables()
        {
            if ($this->cli) {
                if ($GLOBALS['argc'] > 3) {
                    parse_str($GLOBALS['argv'][3], $this->variables);
                }
            } else {
                switch ($this->method) {
                    case 'get':
                    case 'head':
                        $this->variables = $_GET;
                        break;

                    case 'post':
                        $this->variables = $this->mixedPost ? array_merge($_GET, $_POST) : $_POST;
                        if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                            $this->rawData =& $GLOBALS['HTTP_RAW_POST_DATA'];
                        } else {
                            $this->rawData = null;
                        }
                        break;

                    case 'put':
                        parse_str(file_get_contents('php://input'), $this->variables);
                        break;
                }
            }

            if ($this->variables === null) {
                $this->variables = array();
            }
        }


        /**
         * Cleans up the URL by replacing any double slashes with single
         * slashes. Doesn't replace double slashes following a colon.
         *
         * @access protected
         * @param string $relativeUrl The URL to clean
         * @return string The cleaned URL
         */
        protected function cleanUrl($relativeUrl)
        {
            return preg_replace('|(?<!:)/{2,}|', '/', trim($relativeUrl));
        }
        

        /**
         * Adds a trailing slash to the URL if it doesn't have an extension.
         *
         * @access protected
         * @return void
         */
        protected function slashUrl()
        {
            if ($this->endSlash && !$this->extension) {
                $this->relativeUrl = rtrim($this->relativeUrl, ' /').'/';
            }
        }


        /**
         * Checks for a re-routed URL using the pre-loaded routes config
         * and replaces any back references in the result.
         *
         * @access protected
         * @return void
         */
        protected function routeUrl()
        {
            if ($this->routes) {
                foreach ($this->routes as $pattern=>$route) {
                    if (preg_match("#{$pattern}#", $this->relativeUrl, $matches)) {
                        if (preg_match_all('#\$([0-9+])#', $route, $replacements)) {
                            foreach ($replacements[1] as $replacement) {
                                $route = str_replace('$'.$replacement, !empty($matches[$replacement]) ? $matches[$replacement] : '', $route);
                            }
                            $route = preg_replace('#/{2,}#', '/', $route);
                        }
                        $this->routedUrl = $route;
                        break;
                    }
                }
            }
        }


        /**
         * Splits the URL into an array of segments. If the URL has been
         * routed then this uses the routed URL. If any segment contains
         * (but does not start with) an equals sign then it will be set
         * as a filter and removed from the URL segments array.
         * For example: /page=1/
         *
         * @access protected
         * @return void
         */
        protected function parseUrl()
        {
            $segments = explode('/', ($this->routedUrl ?: $this->relativeUrl));
            $filters = array();

            foreach ($segments as $key=>$segment) {
                if ($position = strpos($segment, static::FILTER_DELIMITER)) {
                    $filter = substr($segment, 0, $position);
                    $value = substr($segment, $position + 1);

                    if (array_key_exists($filter, $filters)) {
                        if (!is_array($filters[$filter])) {
                            $filters[$filter] = array($filters[$filter]);
                        }
                        $filters[$filter][] = $value;
                    } else {
                        $filters[$filter] = $value;
                    }
                    $segment = null;
                }

                if (!$segment) {
                    unset($segments[$key]);
                }
            }

            $this->segments = array_values($segments);
            $this->filters = $filters;

            if (strpos($this->relativeUrl, '.')) {
                $extSegments = explode('.', $this->relativeUrl);
                $this->extension = end($extSegments);
            } else {
                $this->extension = null;
            }
        }


        //-----------------------------------------------------------------
        //   get and set methods
        //-----------------------------------------------------------------


        /**
         * Returns the CLI flag. The flag can only be set by manually
         * calling the detectCli() method.
         *
         * @access public
         * @return boolean True if the CLI flag has been set
         */
        public function getCli()
        {
            return $this->cli;
        }


        /**
         * Returns the request method.
         *
         * @access public
         * @return string The request method
         */
        public function getMethod()
        {
            $this->initialized || $this->init();
            return $this->method;
        }


        /**
         * Returns the base URL.
         *
         * @access public
         * @return string The base URL
         */
        public function getBaseUrl()
        {
            return $this->baseUrl;
        }


        /**
         * Returns the URL excluding the base URL.
         *
         * @access public
         * @return string The relative URL
         */
        public function getRelativeUrl()
        {
            $this->initialized || $this->init();
            return $this->relativeUrl;
        }


        /**
         * Returns the base URL, the relative URL and optionally the query
         * string if there is one all joined back together.
         *
         * @access public
         * @param boolean $withQueryString Whether to include the query string
         * @param boolean $encodeUrl Whether to encode the URL data
         * @return string The current URL
         */
        public function getFullUrl($withQueryString = true, $encodeUrl = false)
        {
            $this->initialized || $this->init();

            $relativeUrl = $this->baseUrl.$this->relativeUrl;
            if ($withQueryString && $this->method == 'get' && count($this->variables)) {
                $amp = $encodeUrl ? '&amp;' : '&';

                $relativeUrl .= (strpos($relativeUrl, '?') !== false ? $amp : '?');
                $relativeUrl .= http_build_query($this->variables, null, $amp);
            }

            return $relativeUrl;
        }


        /**
         * Returns the file extension of the current page if there is one.
         *
         * @access public
         * @return string The file extension
         */
        public function getExtension()
        {
            $this->initialized || $this->init();
            return $this->extension;
        }


        /**
         * Returns the URL segment at the position passed.
         *
         * @access public
         * @param integer $position The position of the segment to retrieve
         * @return string The URL segment
         */
        public function getSegment($position)
        {
            if (array_key_exists($position, $segments = $this->getSegments())) {
                return $segments[$position];
            }
        }


        /**
         * Returns all the URL segments as an array.
         *
         * @access public
         * @return array The URL segments
         */
        public function getSegments()
        {
            $this->initialized || $this->init();
            return $this->segments;
        }


        /**
         * Returns the value of the URL filter if it exists.
         *
         * @access public
         * @param string $filter The filter to retrieve
         * @return mixed The filter value
         */
        public function getFilter($filter)
        {
            if (array_key_exists($filter, $filters = $this->getFilters())) {
                return $filters[$filter];
            }
        }


        /**
         * Returns all the URL filters as an array.
         *
         * @access public
         * @return array The URL filters
         */
        public function getFilters()
        {
            $this->initialized || $this->init();
            return $this->filters;
        }


        /**
         * Returns the value of the request variable if it exists.
         *
         * @access public
         * @param string $variable The variable to retrieve
         * @return mixed The variable value
         */
        public function getVariable($variable)
        {
            if (array_key_exists($variable, $variables = $this->getVariables())) {
                return $variables[$variable];
            }
        }


        /**
         * Returns all the request variables as an array.
         *
         * @access public
         * @return array The request variables
         */
        public function getVariables()
        {
            $this->initialized || $this->init();
            return $this->variables;
        }


        /**
         * Returns the raw data from HTTP_RAW_POST_DATA.
         *
         * @access public
         * @return string The raw data
         */
        public function getRawData()
        {
            $this->initialized || $this->init();
            return $this->rawData;
        }


        /**
         * Returns whether the site is running on HTTPS.
         *
         * @access public
         * @return boolean True if using HTTPS
         */
        public function getSecure()
        {
            return !empty($_SERVER['HTTPS']);
        }


        /**
         * Sets the custom routes. The segments and filters come from the
         * routed URL if there's a match.
         *
         * @access public
         * @param array $routes The custom routes in url => route format
         * @return void
         */
        public function setRoutes($routes)
        {
            $this->routes = $routes;
        }

        //-----------------------------------------------------------------
        //   magic methods
        //-----------------------------------------------------------------


        /**
         * Called when the router is cloned because the CLI property
         * should never be cloned.
         *
         * @access public
         * @return void
         */
        public function __clone()
        {
            $this->cli = false;
        }
    }
