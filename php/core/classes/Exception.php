<?php
    namespace Phork\Core;

    /**
     * All Phork classes should throw a custom exception or an extension
     * thereof. Exceptions are handled by the error class. This should
     * be aliased in the bootstrap to \PhorkException.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Exception extends \Exception
    {
        
    }
