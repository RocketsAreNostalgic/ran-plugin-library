<?php
/**
 * EnqueuePublic class adds scripts with the wp_enqueue_scripts WP hook.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\EnqueueAccessory\EnqueueInterface;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @since 0.1.0
 * @package Ran\PluginLib\EnqueueAccessory
 */
class EnqueuePublic implements EnqueueInterface {
	use WPWrappersTrait;
	use WPWrappersTrait;

	private ScriptsHandler $scripts_handler;
	private ScriptModulesHandler $script_modules_handler;
	private StylesHandler $styles_handler;
	private ?MediaHandler $media_handler = null;
	private bool $owns_media_handler     = false;
	private Logger $logger;

	public function __construct(
		ConfigInterface $config,
		?ScriptsHandler $scripts_handler = null,
		?ScriptModulesHandler $script_modules_handler = null,
		?StylesHandler $styles_handler = null,
		?MediaHandler $media_handler = null
	) {
		$this->logger = $config->get_logger();
		$context      = __CLASS__ . '::' . __METHOD__;

		if (is_admin()) {
			if ($this->logger->is_active()) {
				$this->logger->debug("{$context} - Not on public page, bailing.");
			}
			return;
		}

		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - On public page, proceeding to set up asset handlers.");
		}

		$this->scripts_handler        = $scripts_handler        ?? new ScriptsHandler($config);
		$this->script_modules_handler = $script_modules_handler ?? new ScriptModulesHandler($config);
		$this->styles_handler         = $styles_handler         ?? new StylesHandler($config);
		if ($media_handler !== null) {
			$this->media_handler = $media_handler;
		} else {
			$this->media_handler      = new MediaHandler($config);
			$this->owns_media_handler = true;
			$this->register_media_picker_assets($config);
		}
	}

	/**
	 * Returns the ScriptsHandler instance.
	 *
	 * @return ScriptsHandler
	 */
	public function scripts(): ScriptsHandler {
		return $this->scripts_handler;
	}

	/**
	 * Returns the ScriptModulesHandler instance.
	 *
	 * @return ScriptModulesHandler
	 */
	public function script_modules(): ScriptModulesHandler {
		return $this->script_modules_handler;
	}

	/**
	 * Returns the StylesHandler instance.
	 *
	 * @return StylesHandler
	 */
	public function styles(): StylesHandler {
		return $this->styles_handler;
	}

	/**
	 * Returns the MediaHandler instance.
	 *
	 * @return MediaHandler
	 */
	public function media(): MediaHandler {
		return $this->media_handler;
	}

	/**
	 * Hooks the stage() method to wp_enqueue_scripts.
	 */
	public function load(): void {
		$context = __CLASS__ . '::' . __METHOD__;

		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - Hooking stage() to wp_enqueue_scripts.");
		}
		$this->_do_add_action('wp_enqueue_scripts', array($this, 'stage'));
	}

	/**
	 * Enqueues all registered scripts, styles, and media assets.
	 */
	public function stage(): void {
		$context = __CLASS__ . '::' . __METHOD__;
		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - Staging assets on wp_enqueue_scripts.");
		}
		$this->scripts_handler->stage();
		$this->script_modules_handler->stage();
		$this->styles_handler->stage();
		if ($this->owns_media_handler && $this->media_handler !== null) {
			$mediaAssets = $this->media_handler->get_info()['assets'] ?? array();
			$this->media_handler->stage($mediaAssets);
		}
	}

	/**
	 * Registers media picker assets.
	 *
	 * @param ConfigInterface $config The plugin configuration.
	 */
	private function register_media_picker_assets(ConfigInterface $config): void {
		if ($this->media_handler === null) {
			return;
		}

		$this->media_handler->add(array(
		    array(
		        'args' => array(),
		        'hook' => 'wp_enqueue_scripts',
		    ),
		));

		$cfg     = $config->get_config();
		$baseUrl = isset($cfg['URL']) ? rtrim((string) $cfg['URL'], '/\\') : '';
		if ($baseUrl === '') {
			return;
		}

		$version   = isset($cfg['Version']) ? (string) $cfg['Version'] : false;
		$assetUrl  = $baseUrl . '/inc/Forms/assets/media-picker.js';
		$assetArgs = array(
		    'handle' => 'ran-forms-media-picker',
		    'src'    => $assetUrl,
		    'deps'   => array('media-editor', 'media-views'),
		    'hook'   => 'wp_enqueue_scripts',
		);
		if ($version !== false) {
			$assetArgs['version'] = $version;
		}

		$this->scripts_handler->add(array($assetArgs));
	}
}
