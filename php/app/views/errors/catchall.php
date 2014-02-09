<?php
	print $statusCode.': '.$statusString;
	
	//if included from fatal.php and in verbose mode then print the full exception
	$verbose = !empty(\Phork::instance()->config) && \Phork::config()->error->verbose;
	if (isset($exception) && $verbose) {
		printf('<pre>%s</pre>', print_r($exception, 1));
	}
