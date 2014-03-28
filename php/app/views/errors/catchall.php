<?php
    if (isset($statusCode) && isset($statusString)) {
        print htmlentities($statusCode.': '.$statusString);
    } else {
        print 'Fatal error';
    }
    
    //if included from fatal.php and in verbose mode then print the full exception
    $verbose = !empty(\Phork::instance()->config) && \Phork::config()->error->verbose;
    if (isset($exception) && $verbose) {
        printf('<pre>%s</pre>', print_r($exception, 1));
    }
