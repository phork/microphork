<?php
    namespace Phork\Core;

    /**
     * The encoder class is used to transform array and objects into
     * another format (eg. JSON or XML) by dispatching the original
     * data to one or more handler classes.
     *
     * <code>
     *   $encoder = new Encoder();
     *   $encoder->addHandler('json', new Encode\Json());
     *   $encoder->addHandler('xml', new Encode\Xml());
     *   $encoder->encode($array);
     * </code>
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Encoder extends Dispatcher
    {
        protected $instanceOf = '\\Phork\\Core\\Encoder\\Interfaces\\Handler';
    }
