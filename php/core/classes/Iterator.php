<?php
    namespace Phork\Core;

    /**
     * The iterator class stores a collection of items. This implements
     * PHP's built in iterator interface.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Iterator implements \Iterator, \Countable, \ArrayAccess
    {
        protected $items = array();
        protected $count = 0;
        protected $cursor = 0;


        /**
         * Validates the item being added to the list. This allows any
         * type of item to be added but can be extended to add certain
         * restrictions.
         *
         * @access protected
         * @param mixed $item The record to validate
         * @return boolean True if allowed
         */
        protected function allowed($item)
        {
            return true;
        }

        /**
         * Returns true if the cursor position is valid.
         *
         * @access public
         * @return boolean True if valid
         */
        public function valid()
        {
            return $this->cursor >= 0 && $this->cursor < $this->count;
        }

        //-----------------------------------------------------------------
        //   cursor moving methods
        //-----------------------------------------------------------------

        /**
         * Rewinds the cursor and returns the previous item from the list
         * The minimum cursor value is one before the first actual position.
         *
         * @access public
         * @return mixed The previous item if not at the beginning
         */
        public function prev()
        {
            if ($this->cursor > 0) {
                $this->cursor--;

                return $this->current();
            } else {
                $this->cursor = -1;
            }
        }

        /**
         * Advances the cursor and returns the next item from the list.
         * The maximum cursor value is one past the last actual position.
         *
         * @access public
         * @return mixed The next item if not at the end
         */
        public function next()
        {
            if ($this->cursor < $this->count) {
                $this->cursor++;

                return $this->current();
            } else {
                $this->cursor = $this->count;
            }
        }

        /**
         * Rewinds the cursor to the beginning of the list.
         *
         * @access public
         * @return void
         */
        public function rewind()
        {
            $this->cursor = 0;
        }

        /**
         * Moves the cursor to the end of the list.
         *
         * @access public
         * @return void
         */
        public function end()
        {
            $this->cursor = $this->count - 1;
        }

        /**
         * Moves the cursor to the position passed.
         *
         * @access public
         * @param integer $position The position to seek to
         * @return boolean True if the position exists
         */
        public function seek($position)
        {
            if ($this->offsetExists($position)) {
                $this->cursor = $position;

                return true;
            }
        }

        /**
         * Returns the current item from the list and advances the cursor.
         * This should not be used with the remove or modify methods here
         * (or any other method that relies on the cursor) because the cursor
         * will have been interated and will be on next item.
         *
         * @access public
         * @return array The array of position and item
         */
        public function each()
        {
            if ($this->offsetExists($this->cursor)) {
                $position = $this->cursor;
                $item = $this->items[$this->cursor];
                $this->next();

                return array($position, $item);
            }
        }

        //-----------------------------------------------------------------
        //   modification methods
        //-----------------------------------------------------------------

        /**
         * Appends an item to the list and increments the count.
         *
         * @access public
         * @param mixed $item The item to append
         * @return integer The array key of the appended item
         */
        public function append($item)
        {
            if ($this->allowed($item)) {
                $this->items[$this->count++] = $item;

                return $this->count - 1;
            }
        }

        /**
         * Inserts an item at a specific position and shifts all the
         * other items accordingly.
         *
         * @access public
         * @param integer $position The position to insert the item
         * @param mixed $item The item to insert
         * @return integer The array key of the inserted item
         */
        public function insert($position, $item)
        {
            if ($this->allowed($item)) {
                array_splice($this->items, $position, 1, array_merge(array($item), array_slice($this->items, $position, 1)));
                $this->count++;

                return $position;
            }
        }

        /**
         * Inserts an item before the position passed.
         *
         * @access public
         * @param integer $position The position to insert the item before
         * @param mixed $item The item to insert
         * @return integer The array key of the inserted item
         */
        public function before($position, $item)
        {
            if ($this->offsetExists($position)) {
                return $this->insert($position, $item);
            }
        }

        /**
         * Inserts an item after the position passed.
         *
         * @access public
         * @param integer $position The position to insert the item after
         * @param mixed $item The item to insert
         * @return integer The array key of the inserted item
         */
        public function after($position, $item)
        {
            if ($this->offsetExists($position)) {
                return $this->insert($position + 1, $item);
            }
        }

        /**
         * Modifies the current item in the list.
         *
         * @access public
         * @param mixed $item The new item
         * @return boolean True on success
         */
        public function modify($item)
        {
            return $this->offsetSet($this->cursor, $item);
        }

        /**
         * Removes the current item from the list.
         *
         * @access public
         * @return boolean True on success
         */
        public function remove()
        {
            return $this->offsetUnset($this->cursor);
        }

        /**
         * Clears out the list and resets the cursor.
         *
         * @acccess public
         * @return void
         */
        public function clear()
        {
            $this->items = array();
            $this->count = 0;
            $this->rewind();
        }

        //-----------------------------------------------------------------
        //   retrieval methods
        //-----------------------------------------------------------------

        /**
         * Returns the current item from the list.
         *
         * @access public
         * @return mixed The current item
         */
        public function current()
        {
            if ($this->offsetExists($this->cursor)) {
                return $this->items[$this->cursor];
            }
        }

        /**
         * Returns the first item in the list.
         *
         * @access public
         * @return mixed The first item or null if it doesn't exist
         */
        public function first()
        {
            return $this->offsetExists(0) ? $this->items[0] : null;
        }

        /**
         * Returns the last item in the list.
         *
         * @access public
         * @return mixed The last item or null if it doesn't exist
         */
        public function last()
        {
            return $this->offsetExists($this->count - 1) ? $this->items[$this->count - 1] : null;
        }

        /**
         * Returns all the items in the list.
         *
         * @access public
         * @return array The array of items
         */
        public function items()
        {
            return $this->items;
        }

        /**
         * Returns the count of the items in the list.
         *
         * @access public
         * @return integer The item count
         */
        public function count()
        {
            return $this->count;
        }

        /**
         * Returns the position of the cursor.
         *
         * @access public
         * @return integer The cursor position
         */
        public function key()
        {
            return $this->cursor;
        }

        //-----------------------------------------------------------------
        //   offset methods
        //-----------------------------------------------------------------

        /**
         * Checks if an item exists by its position.
         *
         * @access public
         * @param integer $position The position of the item to check for
         * @return boolean True if it exists
         */
        public function offsetExists($position)
        {
            return array_key_exists($position, $this->items);
        }

        /**
         * Returns the item at the position passed if it exists.
         *
         * @access public
         * @param integer $position The position of the item to get
         * @return mixed The item at the position passed
         */
        public function offsetGet($position)
        {
            if ($this->offsetExists($position)) {
                return $this->items[$position];
            }
        }
        
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
            if ($this->allowed($item)) {
                if ($this->offsetExists($position)) {
                    $this->items[$position] = $item;
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
            if ($this->offsetExists($position)) {
                unset($this->items[$position]);
                $this->items = array_values($this->items);
                $this->count--;
                
                if ($this->cursor >= $this->count) {
                    $this->cursor--;
                }
                
                return true;
            }
        }
    }
