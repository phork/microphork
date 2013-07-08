<?php
	return array(
		'env'			=> 'dev',
		
		'language'		=> array(
			'language'		=> 'english'
		),
		
		'error'			=> array(
			'handlers'		=> array(
				'log'			=> array(
					'active'		=> true
				)
			)
		),
		
		'debug'			=> array(
			'handlers'		=> array(
				'display'		=> array(
					'active'		=> true	
				),
				'log'			=> array(
					'active'		=> true
				)
			)
		),
		
		'interfaces'	=> array(
			'browser'		=> array(
				'debug'			=> true
			)
		)
	);