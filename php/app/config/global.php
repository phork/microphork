<?php

    return array(
        'language'		=> array(
            'language'		=> null,
            'cachepath'		=> null
        ),

        'error'			=> array(
            'handlers'		=> array(
                'log'			=> array(
                    'active'	=> false,
                    'class'		=> '\Phork\Core\Error\Log',
                    'params'	=> array(
                        'logfile'	=> LOG_PATH.'error.log',
                        'verbose'	=> true
                    )
                )
            )
        ),

        'debug'			=> array(
            'handlers'		=> array(
                'display'		=> array(
                    'active'		=> false,
                    'class'			=> '\Phork\Core\Debug\Display',
                    'params'		=> array(
                        'html'			=> true,
                        'verbose'		=> true
                    )
                ),
                'log'			=> array(
                    'active'		=> false,
                    'class'			=> '\Phork\Core\Debug\Log',
                    'params'		=> array(
                        'logfile'		=> LOG_PATH.'debug.log',
                        'verbose'		=> true
                    )
                )
            )
        ),

        'router'		=> array(
            'urls'			=> array(
                'site'			=> !empty($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '',
                'secure'		=> !empty($_SERVER['HTTP_HOST']) ? 'https://'.$_SERVER['HTTP_HOST'] : ''
            ),
            'routes'		=> array(
                '^/error/([0-9]{3}/?)'	=> '/home/fatal/$1'
            )
        )
    );
