<?php
/**
 * Example: Admin styles using EnqueueAdmin and StylesHandler (happy path)
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueueAdmin($config);
	$enqueue->load(); // hooks stage() to admin_enqueue_scripts

	add_action('init', function() use ($enqueue) {
		$enqueue->styles()->add(array(
		    array(
		        'handle' => 'my-plugin-admin-style',
		        'src'    => plugins_url('assets/css/admin.css', __FILE__),
		    ),
		));
	});
});
