<?php
/**
 * Example: Public scripts using EnqueuePublic and ScriptsHandler
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;
use Ran\PluginLib\EnqueueAccessory\AssetType;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

// Initialize on plugins_loaded so registrations are in place before target hooks
add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueuePublic($config);

	// Hook stage() to wp_enqueue_scripts
	$enqueue->load();

	// Add assets on init (application layer)
	add_action('init', function() use ($enqueue) {
		$enqueue->scripts()->add(array(
		    array(
		        'handle' => 'my-plugin-public',
		        'src'    => plugins_url('assets/js/public.js', __FILE__),
		        // 'deps' => ['wp-element'],
		        // 'ver'  => '1.0.0',
		        // 'in_footer' => true,
		    ),
		));
		// stage() will run automatically on wp_enqueue_scripts via load()
	});
});
