<?php
    namespace Phork\Core\Debug\Handlers;

    /**
     * The debug handler interface makes sure each debug handler has
     * a proper constructor and a log method.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    interface HandlerInterface
    {
        public function __construct($params = array());
        public function log();
    }
