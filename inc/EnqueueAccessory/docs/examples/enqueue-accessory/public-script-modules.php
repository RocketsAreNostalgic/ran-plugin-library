<?php
/**
 * Example: Public script modules using EnqueuePublic and ScriptModulesHandler
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;
use Ran\PluginLib\EnqueueAccessory\AssetType;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueuePublic($config);
	$enqueue->load();

	add_action('init', function() use ($enqueue) {
		$enqueue->script_modules()->add(array(
		    array(
		        'handle' => 'my-plugin-module',
		        'src'    => plugins_url('assets/js/module.js', __FILE__),
		        'type'   => 'module',
		    ),
		));
	});
});
