<?php
    namespace Phork\Core\Controllers;

    /**
     * The controller handles redirecting to another page with an optional
     * status code.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package \Phork\Core
     */
    class Redirect
    {
        /**
         * This is called from the bootstrap and it determines the redirect
         * URL and status (eg. 301).
         *
         * @access public
         * @return void
         */
        public function run()
        {
            \Phork::event()->trigger('controller.run.before', null, true);
            
            $status = \Phork::router()->getFilter('status');
            $redirect = preg_replace('#/redirect(/status=[0-9]{3})?/#', '/', \Phork::router()->getRoutedUrl());
            
            \Phork::output()->setStatusCode($status ?: 200)->addHeader('location: '.$redirect);
            \Phork::event()->trigger('controller.run.after', null, true);
        }
    }
