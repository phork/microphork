<?php
    return array(
        'env'               => 'dev',

        'error'             => array(
            'verbose'           => true,
            'backtrace'         => true,
            'handlers'          => array(
                'log'               => array(
                    'active'            => true
                )
            )
        ),

        'debug'             => array(
            'handlers'          => array(
                'display'           => array(
                    'active'            => true
                ),
                'log'               => array(
                    'active'            => true
                )
            )
        )
    );
