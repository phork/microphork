<?php
	return array(
		'env'			=> null,
		
		'language'		=> array(
			'language'		=> null,
			'cachepath'		=> null
		),
		
		'error'			=> array(
			'verbose'		=> true,
			'backtrace'		=> true,
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
			'defaults'		=> array(
				'controller'	=> 'home'
			),
			'urls'			=> array(
				'site'			=> !empty($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '',
				'secure'		=> !empty($_SERVER['HTTP_HOST']) ? 'https://'.$_SERVER['HTTP_HOST'] : '',
				'base'			=> '',
			),
			'routes'		=> array(
				'^/error/([0-9]{3}/?)'	=> '/home/fatal/$1'
			)
		),
		
		'encoder'		=> array(
			'handlers'		=> array(
				'xml'			=> array(
					'class'			=> '\Phork\Core\Encoder\Xml',
					'params'		=> array()
				),
				'json'			=> array(
					'class'			=> '\Phork\Core\Encoder\Json',
					'params'		=> array()
				),
				'jsonp'			=> array(
					'class'			=> '\Phork\Core\Encoder\Jsonp',
					'params'		=> array()
				)
			)
		),
		
		'interfaces'	=> array(
			'browser'		=> array(
				'debug'			=> false
			),
			'api'			=> array(
				'debug'			=> false,
				'defaults'		=> array(
					'encoder'		=> 'json'
				)
			)
		)
	);