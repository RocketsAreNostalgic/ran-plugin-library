<?php
/**
 * Example: Admin scripts using EnqueueAdmin and ScriptsHandler
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueueAdmin($config);
	$enqueue->load(); // Hooks stage() to admin_enqueue_scripts

	add_action('init', function() use ($enqueue) {
		$enqueue->scripts()->add(array(
		    array(
		        'handle' => 'my-plugin-admin',
		        'src'    => plugins_url('assets/js/admin.js', __FILE__),
		    ),
		));
	});
});
