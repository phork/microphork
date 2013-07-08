<?php
	return array(
		'auth'			=> array(
			'handlers'		=> array(
				'spoofed'		=> array(
					'active'		=> true,
					'class'			=> '\Phork\Pkg\Auth\Spoofed',
					'params'		=> array(
						'userid'		=> 1,
						'username'		=> 'phork',
						'authenticated'	=> true
					)
				)
			)
		)
	);