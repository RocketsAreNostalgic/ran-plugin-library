<?php
/**
 * Example: Public styles using EnqueuePublic and StylesHandler (happy path)
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueuePublic($config);
	$enqueue->load(); // hooks stage() to wp_enqueue_scripts

	add_action('init', function() use ($enqueue) {
		$enqueue->styles()->add(array(
		    array(
		        'handle' => 'my-plugin-style',
		        'src'    => plugins_url('assets/css/public.css', __FILE__),
		    ),
		));
	});
});
