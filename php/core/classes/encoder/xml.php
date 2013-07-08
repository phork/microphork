<?php
	namespace Phork\Core\Encoder;
	
	/**
	 * Encodes an array or object to XML data. This should be used
	 * as a handler for the Encoder class.
	 *
	 * @author Elenor Collings <elenor@phork.org>
	 * @package phork
	 * @subpackage core
	 */
	class Xml implements Interfaces\Handler {
		
		protected $numericReplacements;
		protected $numericPrefix;
		protected $formatCallback;
		protected $includeKeys;
		
		protected $dom;
		
		
		/**
		 * Sets up the handler's params if there are any.
		 *
		 * @access public
		 * @param array $params An array of params to set for each property
		 * @return void
		 */
		public function __construct($params = array()) {
			foreach ($params as $key=>$value) {
				if (property_exists($this, $key)) {
					$this->$key = $value;
				}
			}
		}
		
		
		/**
		 * Encodes the data into XML.
		 *
		 * @access public
		 * @param mixed $source The array or object to encode
		 * @param array $args Custom formatting options
		 * @return string The XML data.
		 */
		public function encode($source, $args = array()) {
			$rootNode = isset($args['rootNode']) ? $args['rootNode'] : 'root';
			
			$this->numericReplacements = isset($args['numericReplacements']) ? $args['numericReplacements'] : array();
			$this->numericPrefix = isset($args['numericPrefix']) ? $args['numericPrefix'] : 'node';
			$this->formatCallback = isset($args['formatCallback']) ? $args['formatCallback'] : null;
			$this->includeKeys = isset($args['includeKeys']) ? $args['includeKeys'] : false;
		
			$this->dom = new \DOMDocument();
			$this->dom->formatOutput = true;
			$this->dom->appendChild($root = $this->dom->createElement($rootNode));
			
			$this->build($source, $root);
			return $this->dom->saveXML();
		}
		
		
		/**
		 * Builds the XML from an array or object and appends it to the parent
		 * parent node passed. If the node name is numeric and it has been replaced
		 * with a string, and if $includeKeys is true then this adds the original 
		 * numeric value as a key attribute.
		 *
		 * @access protected
		 * @param mixed $source The array or object of data to turn into XML
		 * @param object $parent The object to attach the node(s) to
		 * @return void
		 */
		protected function build($source, $parent) {
			foreach ($source as $key=>$source) {
				$realKey = $this->node($key, $parent);
				
				if ($recurse = (is_array($source) || is_object($source))) {
					$parent->appendChild($child = $this->dom->createElement($realKey));
				} else {
					$parent->appendChild($child = $this->dom->createElement($realKey))->appendChild($this->dom->createTextNode($source));
				}
				
				if ($this->includeKeys && ($realKey != "$key")) {
					$child->appendChild($idNode = $this->dom->createAttribute('key'));
					$idNode->appendChild($this->dom->createTextNode($key));
				}
				
				if ($recurse) {
					$this->build($source, $child);
				}
			}
		}
		
		
		/**
		 * Returns the node name to use. If the node is numeric this checks the name
		 * of the parent node and looks in the numeric replacement array to see if 
		 * there's a default node name to use.
		 *
		 * @access protected
		 * @param mixed $key The key to turn into a node name
		 * @param object $parent The parent node
		 * @return string The formatted key to use
		 */
		protected function node($key, $parent) {
			if (!is_numeric($key)) {
				$realKey = $key;
			} else if (!empty($this->numericReplacements[$parentNode = $parent->nodeName])) {
				$realKey = $this->numericReplacements[$parentNode];
			} else {
				$realKey = $this->numericPrefix;
			}
			
			if ($this->formatCallback) {
				$realKey = call_user_func_array($this->formatCallback, array($realKey, $parent->nodeName));
			}
			
			return $realKey;
		}
		
		
		/**
		 * Returns the header to send for XML data.
		 *
		 * @access public
		 * @return string The header for XML data
		 */
		public function getHeader() {
			return 'Content-type: text/xml';
		}
	}