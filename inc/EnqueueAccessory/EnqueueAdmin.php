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

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;

/**
 * Class for handling admin script and style enqueuing.
 *
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @since 1.0.0
 * @package Ran\PluginLib\EnqueueAccessory
 */
class EnqueueAdmin implements EnqueueInterface {
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
		$this->scripts_handler = $scripts_handler ?? new ScriptsHandler($config);
		$this->styles_handler  = $styles_handler  ?? new StylesHandler($config);
		$this->media_handler   = $media_handler   ?? new MediaHandler($config);
		$this->logger          = $config->get_logger();
	}

	public function load(): void {
		if ($this->logger->is_active()) {
			$this->logger->debug('EnqueueAdmin::load() - Method entered.');
		}

		if (!is_admin()) {
			if ($this->logger->is_active()) {
				$this->logger->debug('EnqueueAdmin::load() - Not an admin request. Bailing.');
			}
			return;
		}

		add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	}

	public function enqueue(): void {
		if ($this->logger->is_active()) {
			$this->logger->debug('EnqueueAdmin::enqueue() - Delegating to handlers.');
		}
		$this->scripts_handler->enqueue();
		$this->styles_handler->enqueue();
		$this->media_handler->enqueue();
	}

	public function add_scripts(array $scripts): self {
		$this->scripts_handler->add_scripts($scripts);
		return $this;
	}

	public function add_styles(array $styles): self {
		$this->styles_handler->add_styles($styles);
		return $this;
	}

	public function add_media(array $media): self {
		$this->media_handler->add_media($media);
		return $this;
	}

	public function enqueue_scripts(): self {
		$this->scripts_handler->enqueue_scripts();
		return $this;
	}

	public function enqueue_styles(): self {
		$this->styles_handler->enqueue_styles();
		return $this;
	}

	public function enqueue_media(array $media = array()): self {
		$configs = $this->media_handler->get_media_tool_configs();
		$this->media_handler->enqueue_media($media ?: $configs['general']);
		return $this;
	}

	public function add_inline_scripts( array $inline_scripts_to_add ): self {
		$this->scripts_handler->add_inline_scripts( $inline_scripts_to_add );
		return $this;
	}

	public function add_inline_styles( array $inline_styles_to_add ): self {
		$this->styles_handler->add_inline_styles( $inline_styles_to_add );
		return $this;
	}
}
