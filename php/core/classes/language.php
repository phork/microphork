<?php
	namespace Phork\Core;
	
	/**
	 * Loads in a set of language translation files and translates from
	 * the default application language to another language. The language
	 * file format can either be in human-readable default/replace format
	 * or can be pre-generated into an array of replacements keyed by the
	 * original string.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	class Language {
	
		protected $language;
		protected $translations;
		protected $filepaths;
		protected $cachepath;
		
		
		/**
		 * Returns a translated string. This can have multiple arguments
		 * passed in addition to the string in a printf format. This allows
		 * translation of a base string without having to translate every 
		 * variation.
		 *
		 * @access public
		 * @param string $string The string to translate
		 * @return string The translated string
		 */
		public function translate($string) {
			if (!empty($this->translations[$string])) {
				$string = $this->translations[$string];
			}
			if (count($args = func_get_args()) > 1) {
				$args[0] = $string;
				$string = call_user_func_array('sprintf', $args);
			}
			
			return $string;
		}
		
		
		/**
		 * Returns the list of language files in the file path passed.
		 *
		 * @access protected
		 * @param string $filepath The absolute file path excluding the language dir
		 * @return array The array of absolute file paths
		 */
		protected function files($filepath) {
			if ($filepath && $this->language) {
				return glob($filepath.$this->language.'/*.lang');
			}
		}
		
		
		/**
		 * Loads the replacements into the object from the cache or set of 
		 * language files. This creates an array of replacement values.
		 *
		 * @access protected
		 * @return void
		 */
		protected function load() {
			if ($this->cachepath) {
				if (file_exists($cache = $this->cachepath.'/'.$this->language.'.php')) {
					$this->translations = include($cache);
				}
			} else {
				if (!empty($this->filepaths)) {
					foreach ($this->filepaths as $filepath) {
						$this->loadFilePath($filepath);
					}
				}
			}
		}
		
		
		/**
		 * Loads a set of replacements from a single file path.
		 *
		 * @access protected
		 * @param string $filepath The path to the files to load
		 * @return void
		 */
		protected function loadFilePath($filepath) {
			if ($filepaths = $this->files($filepath)) {
				foreach ($filepaths as $file) {
					$contents = file_get_contents($file);
					
					preg_match_all('/^default: (.*)$/Um', $contents, $default);
					$default = $default[1];
					
					preg_match_all('/^replace: (.*)$/Um', $contents, $replace);
					$replace = $replace[1];
					
					if ($default) {
						if (count($default) == count($replace)) {
							$this->translations = array_merge($this->translations, array_combine($default, $replace));
						} else {
							throw new \PhorkException(sprintf('The %s language file %s has mismatched definitions and replacements', $this->language, basename($file)));
						}
					}
				}
			}
		}
		
		
		/**
		 * Adds a new file path and immediately adds each language file
		 * in it to the translation library if the language has been set.
		 *
		 * @access public
		 * @param string $filepath The path to the files to language directory
		 */
		public function addFilePath($filepath) {
			$this->filepaths || $this->filepaths = array();
			$this->filepaths[] = $filepath;
			
			if ($this->language) {
				$this->loadFilePath($filepath);
			}
		}
		
		
		/**
		 * Sets the file paths to the language directories, excluding
		 * the specific language directory. 
		 * For example /path/to/lang not /path/to/lang/english.
		 *
		 * @access public
		 * @param array $filepaths The file paths to the language directories
		 * @return void
		 */
		public function setFilePaths($filepaths) {
			$this->filepaths = $filepaths;
		}
		
		
		/**
		 * Sets the path to the cached language files.
		 *
		 * @access public
		 * @param string $cachepath The file path to the cache directory
		 * @return void
		 */
		public function setCachePath($cachepath) {
			$this->cachepath = $cachepath;
		}
		
		
		/**
		 * Sets the language to use for the translations and includes
		 * and parses the language files.
		 *
		 * @access public
		 * @param string $language The language to use
		 * @return void
		 */
		public function setLanguage($language) {
			$this->translations = array();
			if ($this->language = $language) {
				$this->load();
			}
		}
	}