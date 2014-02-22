<?php
    namespace Phork\Core;

    /**
     * The debug class is used to output or log any debugging data by
     * dispatching the debugging data to one or more handler classes.
     * If no handlers have been defined then the debugging data is
     * disregarded.
     *
     * <code>
     *   $debug = new Debug();
     *   $debug->addHandler('log', new Debug\Handlers\Log('/path/to/logfile'));
     *   $debug->addHandler('display', new Debug\Handlers\Display());
     *   $debug->log('Debugged!');
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Debug extends Dispatcher
    {
        protected $instanceOf = '\\Phork\\Core\\Debug\\Handlers\\HandlerInterface';
    }
