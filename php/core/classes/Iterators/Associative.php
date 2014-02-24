<?php
    namespace Phork\Core\Iterators;

    /**
     * The associative iterator class stores a collection of items that
     * can be accessed in a standardized way. This stores each item with
     * an associated key which will remain unchanged. If no key is passed
     * when adding an item one will be generated. The key is useful when
     * referring to a specific item that may have been moved around in the
     * array.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Associative extends \Phork\Core\Iterator
    {
        protected $keys = array();


        /**
         * Generates a unique key for an item.
         *
         * @access protected
         * @return string The unique key
         */
        protected function genKey()
        {
            do {
                $key = '__'.rand();
            } while (in_array($key, $this->keys));

            return $key;
        }
        

        /**
         * Extracts the key and the value from the item. If a key doesn't
         * exist this will generate one.
         *
         * @access protected
         * @param mixed $item The key/value pair or just the item value
         * @param boolean $genKey Whether to generate a key if one doesn't exist
         * @return array The key/value pair
         */
        protected function extract($item, $genKey = true)
        {
            if (is_array($item)) {
                list($key, $value) = $item;
            } else {
                $value = $item;
                $key = null;
            }

            if ((!$key && $key !== 0) && $genKey) {
                $key = $this->genKey();
            }

            return array($key, $value);
        }


        //-----------------------------------------------------------------
        //   cursor moving methods
        //-----------------------------------------------------------------


        /**
         * Moves the cursor to the position of the key passed.
         *
         * @access public
         * @param string $key The key to seek to
         * @return boolean True if the position exists
         */
        public function seek($key)
        {
            if (($position = $this->keyOffset($key)) !== false) {
                $this->cursor = $position;

                return true;
            }
        }
        

        /**
         * Returns the current item from the list and advances the cursor.
         * This should not be used with the remove or modify methods here
         * (or any other method that relies on the cursor) because the cursor
         * will have been iterated and will be on next item.
         *
         * @access public
         * @return array The array of key and item
         */
        public function each()
        {
            if ($this->cursor < $this->count) {
                $key = $this->keys[$this->cursor];
                $item = $this->items[$this->cursor];
                $this->next();

                return array($key, $item);
            }
        }


        //-----------------------------------------------------------------
        //   modification methods
        //-----------------------------------------------------------------


        /**
         * Appends an item to the list and increments the count.
         *
         * @access public
         * @param array $item The key and item to append
         * @return string The array key of the appended item
         */
        public function append($item)
        {
            list($key, $value) = $this->extract($item);
            if ($this->allowed($value)) {
                $this->keys[$this->count] = $key;
                $this->items[$this->count] = $value;
                $this->count++;

                return $key;
            }
        }
        

        /**
         * Inserts an item at a specific position and shifts all the
         * other items accordingly.
         *
         * @access public
         * @param integer $position The position to insert the item
         * @param array $item The key and item to append
         * @return string The array key of the appended item
         */
        public function insert($position, $item)
        {
            list($key, $value) = $this->extract($item);
            if ($this->allowed($value)) {
                array_splice($this->keys, $position, 1, array_merge(array($key), array_slice($this->keys, $position, 1)));
                array_splice($this->items, $position, 1, array_merge(array($value), array_slice($this->items, $position, 1)));
                $this->count++;

                return $key;
            }
        }
        

        /**
         * Inserts an item before the key passed.
         *
         * @access public
         * @param string $key The key to insert the item before
         * @param mixed $item The item to insert
         * @return string The array key of the inserted item
         */
        public function before($key, $item)
        {
            if ($this->keyExists($key)) {
                return $this->insert($this->keyOffset($key), $item);
            }
        }
        

        /**
         * Inserts an item after the key passed.
         *
         * @access public
         * @param string $key The key to insert the item after
         * @param mixed $item The item to insert
         * @return integer The array key of the inserted item
         */
        public function after($key, $item)
        {
            if ($this->keyExists($key)) {
                return $this->insert($this->keyOffset($key) + 1, $item);
            }
        }
        

        /**
         * Clears out the list and resets the cursor.
         *
         * @access public
         * @return void
         */
        public function clear()
        {
            $this->keys = array();
            parent::clear();
        }


        //-----------------------------------------------------------------
        //   retrieval methods
        //-----------------------------------------------------------------


        /**
         * Returns all the items in the list with their keys.
         *
         * @access public
         * @return array The array of items
         */
        public function items()
        {
            return $this->keys || $this->items ? array_combine($this->keys, $this->items) : array();
        }
        

        /**
         * Returns the key at the position of the cursor.
         *
         * @access public
         * @return string The current key
         */
        public function key()
        {
            if (isset($this->keys[$this->cursor])) {
                return $this->keys[$this->cursor];
            }
        }
        

        /**
         * Returns the position of an item by its key.
         *
         * @access public
         * @param string $key The key to get the position of
         * @return integer The position if it exists
         */
        public function keyOffset($key)
        {
            return array_search($key, $this->keys);
        }
        
        
        /**
         * Returns the key for the offset passed.
         *
         * @access public
         * @param integer $offset The offset to get the key for
         * @return string The key if it exists
         */
        public function offsetKey($offset)
        {
            if (isset($this->keys[$offset])) {
                return $this->keys[$offset];
            }
        }


        //-----------------------------------------------------------------
        //   offset methods
        //-----------------------------------------------------------------


        /**
         * Modifies an item in the list by its position. If no item exists
         * at the position passed it will be appended.
         *
         * @access public
         * @param integer $position The position of the item to modify
         * @param mixed $item The new item to put in its place
         * @return boolean True on success
         */
        public function offsetSet($position, $item)
        {
            list($key, $value) = $this->extract($item, false);
            if ($this->allowed($value)) {
                if ($this->offsetExists($position)) {
                    $this->items[$position] = $value;
                } else {
                    $this->append($item);
                }

                return true;
            }
        }
        

        /**
         * Removes an item by its position, decrements the count and shifts
         * the other items to fill the hole.
         *
         * @access public
         * @param integer $position The position of the item to remove.
         * @return boolean True on success
         */
        public function offsetUnset($position)
        {
            if (parent::offsetUnset($position)) {
                unset($this->keys[$position]);
                $this->keys = array_values($this->keys);

                return true;
            }
        }
        

        //-----------------------------------------------------------------
        //   key methods
        //-----------------------------------------------------------------


        /**
         * Checks if an item exists by its key.
         *
         * @access public
         * @param string $key The key of the item to check for
         * @return boolean True if it exists
         */
        public function keyExists($key)
        {
            return $this->keyOffset($key) !== false;
        }
        

        /**
         * Returns the item with the key passed if it exists.
         *
         * @access public
         * @param string $key The key of the item to get
         * @return mixed The item with the key passed
         */
        public function keyGet($key)
        {
            if (($position = $this->keyOffset($key)) !== false) {
                return $this->items[$position];
            }
        }
        

        /**
         * Modifies the current item in the list by its key.
         *
         * @access public
         * @param string $key The key to modify by
         * @param mixed $item The new item to put in its place
         * @return boolean True on success
         */
        public function keySet($key, $item)
        {
            if (($position = $this->keyOffset($key)) !== false) {
                return $this->offsetSet($position, array($key, $item));
            }
        }
        

        /**
         * Removes an item by its key, decrements the count and shifts
         * the other items to fill the hole.
         *
         * @access public
         * @param string $key The key of the item to remove.
         * @return boolean True on success
         */
        public function keyUnset($key)
        {
            if ($this->keyExists($key) && ($position = $this->keyOffset($key)) !== false) {
                return $this->offsetUnset($position);
            }
        }
    }
