<?php
/**
 * Example: Admin media picker integration using EnqueueAdmin and MediaPickerAssets
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;
use Ran\PluginLib\Forms\Upload\MediaPickerAssets;

/** @var ConfigInterface $config */
// $config = ... obtain your plugin ConfigInterface implementation ...

add_action('plugins_loaded', function() use ($config) {
	$enqueue = new EnqueueAdmin($config);
	$enqueue->load(); // Hooks stage() to admin_enqueue_scripts

	add_action('init', function() use ($enqueue, $config) {
		$shouldLoad = apply_filters('my_plugin_media_picker_should_load', false);
		if ($shouldLoad !== true) {
			return;
		}

		$mediaHandler = $enqueue->media();
		if ($mediaHandler === null) {
			return;
		}

		$assets = new MediaPickerAssets($config, $enqueue->scripts(), $mediaHandler);
		$assets->register();

		$enqueue->scripts()->add(array(
		    array(
		        'handle'   => 'my-plugin-media-picker-config',
		        'hook'     => 'admin_enqueue_scripts',
		        'deps'     => array('ran-forms-media-picker'),
		        'strategy' => 'after',
		        'localize' => array(
		            'name' => 'myPluginMediaPickerConfig',
		            'data' => array(
		                'button' => array(
		                    'text' => __('Select media item', 'my-textdomain'),
		                ),
		                'modalTitle' => __('Media Library', 'my-textdomain'),
		            ),
		        ),
		    ),
		));
	});

	add_action('admin_init', function() {
		// Later, render the form that includes the media picker field.
	});
});

