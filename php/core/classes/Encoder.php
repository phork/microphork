<?php
    namespace Phork\Core;

    /**
     * The encoder class is used to transform arrays and objects into
     * another format (eg. JSON or XML) by dispatching the original
     * data to one or more handler classes.
     *
     * <code>
     *   $encoder = new Encoder();
     *   $encoder->addHandler('json', new Encoder\Handlers\Json());
     *   $encoder->addHandler('xml', new Encoder\Handlers\Xml());
     *   $encoder->encode($array);
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Encoder extends Dispatcher
    {
        protected $instanceOf = '\\Phork\\Core\\Encoder\\Handlers\\HandlerInterface';
        protected $minimum = 1;
    }
