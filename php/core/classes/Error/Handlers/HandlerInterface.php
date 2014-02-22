<?php
    namespace Phork\Core\Error\Handlers;

    /**
     * The error handler interface makes sure each error handler has
     * a proper constructor and the right handle methods.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    interface HandlerInterface
    {
        public function __construct($params = array());
        public function handle($type, $level, $error, $file, $line);
    }
