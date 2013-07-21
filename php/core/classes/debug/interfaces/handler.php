<?php
    namespace Phork\Core\Debug\Interfaces;

    /**
     * The debug handler interface makes sure each debug handler has
     * a proper constructor and a handle method.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    interface Handler
    {
        public function __construct($params = array());
        public function log();
    }
