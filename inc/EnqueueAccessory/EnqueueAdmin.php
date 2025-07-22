<?php
/**
 * EnqueueAdmin class file.
 *
 * This file contains the EnqueueAdmin class for handling admin script and style enqueuing.
 *
 * @package  Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\WPWrappersTrait;
use Ran\PluginLib\EnqueueAccessory\EnqueueInterface;

/**
 * Class for handling admin script and style enqueuing.
 *
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @since 0.1.0
 * @package Ran\PluginLib\EnqueueAccessory
 */
class EnqueueAdmin implements EnqueueInterface {
	use WPWrappersTrait;

	private ScriptsHandler $scripts_handler;
	private StylesHandler $styles_handler;
	private MediaHandler $media_handler;
	private Logger $logger;

	public function __construct(
		ConfigInterface $config,
		?ScriptsHandler $scripts_handler = null,
		?StylesHandler $styles_handler = null,
		?MediaHandler $media_handler = null
	) {
		$this->logger = $config->get_logger();
		$context      = __CLASS__ . '::' . __METHOD__;

		if (!is_admin()) {
			if ($this->logger->is_active()) {
				$this->logger->debug("{$context} - Not on admin page, bailing.");
			}
			return;
		}

		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - On admin page, proceeding to set up asset handlers.");
		}

		$this->scripts_handler = $scripts_handler ?? new ScriptsHandler($config);
		$this->styles_handler  = $styles_handler  ?? new StylesHandler($config);
		// $this->media_handler   = $media_handler   ?? new MediaHandler($config);
	}

	public function scripts(): ScriptsHandler {
		return $this->scripts_handler;
	}

	public function styles(): StylesHandler {
		return $this->styles_handler;
	}

	// public function media(): MediaHandler {
	// 	return $this->media_handler;
	// }

	public function load(): void {
		$context = __CLASS__ . '::' . __METHOD__;

		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - " . __CLASS__ . 'Hooking stage() to admin_enqueue_scripts.');
		}

		$this->_do_add_action('admin_enqueue_scripts', array($this, 'stage'));
	}

	public function stage(): void {
		$context = __CLASS__ . '::' . __METHOD__;
		if ($this->logger->is_active()) {
			$this->logger->debug("{$context} - Delegating to handlers.");
		}
		$this->scripts_handler->stage();
		$this->styles_handler->stage();
		// $this->media_handler->stage_media($this->media_tool_configs);
	}
}
