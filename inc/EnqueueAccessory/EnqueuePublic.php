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

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @since 0.1.0
 * @package Ran\PluginLib\EnqueueAccessory
 */
class EnqueuePublic implements EnqueueInterface {
	use WPWrappersTrait;

	private ScriptsHandler $scripts_handler;
	private ScriptModulesHandler $script_modules_handler;
	private StylesHandler $styles_handler;
	private MediaHandler $media_handler;
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
		// $this->media_handler   = $media_handler   ?? new MediaHandler($config);
	}

	public function scripts(): ScriptsHandler {
		return $this->scripts_handler;
	}

	public function script_modules(): ScriptModulesHandler {
		return $this->script_modules_handler;
	}

	public function styles(): StylesHandler {
		return $this->styles_handler;
	}

	// public function media(): MediaHandler {
	// return $this->media_handler;
	// }

	public function load(): void {
		$context = __CLASS__ . '::' . __METHOD__;

		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - Hooking stage() to wp_enqueue_scripts.");
		}
		$this->_do_add_action('wp_enqueue_scripts', array($this, 'stage'));
	}

	public function stage(): void {
		$context = __CLASS__ . '::' . __METHOD__;
		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - Staging assets on wp_enqueue_scripts.");
		}
		$this->scripts_handler->stage();
		$this->script_modules_handler->stage();
		$this->styles_handler->stage();
		// MediaHandler uses stage_media() instead of stage()
		// $this->media_handler->stage_media($this->media_handler->get_info()['assets'] ?? []);
	}
}
