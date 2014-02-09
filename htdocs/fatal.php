<?php
    if (isset($exception)) {
        $statusCode = $exception->getCode() ?: 500;
        $statusString = $exception->getMessage();
    }
    
    //require the error template which will have access to the status vars
    require VIEW_PATH.'errors/catchall.php';