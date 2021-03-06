<?php
    return array(
        'env'               => null,

        'error'             => array(
            'verbose'           => false,
            'backtrace'         => false,
            'handlers'          => array(
                //handlers should be defined in the app class
            )
        ),

        'router'            => array(
            'defaults'          => array(
                'controller'        => 'home',
                'endslash'          => false,
                'mixedpost'         => false
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
                    'class'             => '\Phork\Core\Encoder\Handlers\Xml',
                    'params'            => array()
                ),
                'json'              => array(
                    'class'             => '\Phork\Core\Encoder\Handlers\Json',
                    'params'            => array()
                ),
                'jsonp'             => array(
                    'class'             => '\Phork\Core\Encoder\Handlers\Jsonp',
                    'params'            => array()
                )
            )
        ),

        'interfaces'        => array(
            'api'               => array(
                'defaults'          => array(
                    'encoder'           => 'json',
                    'meta'              => false
                )
            )
        )
    );
