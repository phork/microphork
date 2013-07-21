<?php
    namespace Phork\Core\Error\Interfaces;

    /**
     * The error handler interface makes sure each error handler has
     * a proper constructor the right handle methods.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    interface Handler
    {
        public function __construct($params = array());
        public function handle($type, $level, $error, $file, $line);
    }
