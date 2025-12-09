<?php
/**
 * Example: Admin media tools using EnqueueAdmin and MediaHandler
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
		$mediaHandler = $enqueue->media();
		if ($mediaHandler === null) {
			return;
		}

		$mediaHandler->add(array(
		    array(
		        'hook' => 'admin_enqueue_scripts',
		        'args' => array(
		            'title'  => __('Select hero image', 'my-textdomain'),
		            'button' => array(
		                'text' => __('Use image', 'my-textdomain'),
		            ),
		        ),
		        'condition' => function() {
		        	if (!function_exists('get_current_screen') || !function_exists('current_user_can')) {
		        		return false;
		        	}
		        	$screen = get_current_screen();
		        	if ($screen === null || $screen->id !== 'toplevel_page_my-plugin-settings') {
		        		return false;
		        	}
		        	return current_user_can('upload_files');
		        },
		    ),
		));

		// When admin_enqueue_scripts fires, MediaHandler::stage() calls wp_enqueue_media() with the configuration above.
	});
});
