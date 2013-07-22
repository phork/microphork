<?php
    return array(
        'env'               => null,

        'error'             => array(
            'handlers'          => array(
                //handlers should be defined in the app class
            )
        ),

        'debug'             => array(
            'handlers'          => array(
                //handlers should be defined in the app class
            )
        ),

        'router'            => array(
            'defaults'          => array(
                'controller'        => 'home'
            ),
            'urls'              => array(
                'base'              => !empty($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''
            ),
            'routes'            => array(
                //routes should be defined in the app class
            )
        ),

        'encoder'           => array(
            'handlers'          => array(
                'xml'               => array(
                    'class'             => '\Phork\Core\Encoder\Xml',
                    'params'            => array()
                ),
                'json'              => array(
                    'class'             => '\Phork\Core\Encoder\Json',
                    'params'            => array()
                ),
                'jsonp'             => array(
                    'class'             => '\Phork\Core\Encoder\Jsonp',
                    'params'            => array()
                )
            )
        ),

        'interfaces'        => array(
            'api'               => array(
                'defaults'          => array(
                    'encoder'           => 'json'
                )
            )
        )
    );
