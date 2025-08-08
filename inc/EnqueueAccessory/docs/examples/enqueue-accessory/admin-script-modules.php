<?php
/**
 * Example: Admin script modules using EnqueueAdmin and ScriptModulesHandler
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueueAdmin($config);
	$enqueue->load();

	add_action('init', function() use ($enqueue) {
		$enqueue->script_modules()->add(array(
		    array(
		        'handle' => 'my-plugin-admin-module',
		        'src'    => plugins_url('assets/js/admin-module.js', __FILE__),
		        'type'   => 'module',
		    ),
		));
	});
});
