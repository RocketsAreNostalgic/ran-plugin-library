<?php
/**
 * EnqueuePublic class adds scripts with the wp_enqueue_scripts WP hook.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
class EnqueuePublic implements EnqueueInterface {
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
			$this->logger->debug('EnqueuePublic::load() - Method entered.');
		}

		if (is_admin()) {
			if ($this->logger->is_active()) {
				$this->logger->debug('EnqueuePublic::load() - In admin, bailing.');
			}
			return;
		}

		if ($this->logger->is_active()) {
			$this->logger->debug('EnqueuePublic::load() - Not in admin, proceeding.');
			$this->logger->debug('EnqueuePublic::load() - Hooking enqueue() to wp_enqueue_scripts.');
		}

		add_action('wp_enqueue_scripts', array($this, 'enqueue'));
		add_action('wp_head', array($this, 'render_head'));
		add_action('wp_footer', array($this, 'render_footer'));

		if ($this->logger->is_active()) {
			$this->logger->debug('EnqueuePublic::load() - Checking for deferred assets.');
		}

		$deferred_hooks = array_unique(array_merge(
			$this->scripts_handler->get_deferred_hooks(AssetType::Script),
			$this->styles_handler->get_deferred_hooks(AssetType::Style),
			$this->media_handler->get_media_deferred_hooks()
		));

		if (!empty($deferred_hooks)) {
			if ($this->logger->is_active()) {
				$this->logger->debug('EnqueuePublic::load() - Deferred assets found. Hooking up actions.');
			}
			foreach ($deferred_hooks as $hook) {
				if ($this->logger->is_active()) {
					$this->logger->debug("EnqueuePublic::load() - Hooking up deferred asset hook: {$hook}");
				}
				$scripts_handler = $this->scripts_handler;
				$styles_handler  = $this->styles_handler;
				$media_handler   = $this->media_handler;

				add_action(
					$hook,
					function () use ($hook, $scripts_handler, $styles_handler, $media_handler) {
						$scripts_handler->enqueue_deferred_scripts($hook);
						$styles_handler->enqueue_deferred_styles($hook);
						$media_handler->enqueue_deferred_media_tools($hook);
					}
				);
			}
		} else {
			if ($this->logger->is_active()) {
				$this->logger->debug('EnqueuePublic::load() - No deferred assets found.');
			}
		}
	}

	public function enqueue(): void {
		$this->scripts_handler->enqueue();
		$this->styles_handler->enqueue();
		$this->media_handler->enqueue();
	}

	public function render_head(): void {
		$this->scripts_handler->render_head();
		$this->styles_handler->render_head();
		$this->media_handler->render_head();
	}

	public function render_footer(): void {
		$this->scripts_handler->render_footer();
		$this->styles_handler->render_footer();
		$this->media_handler->render_footer();
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

	public function stage_scripts(): self {
		$this->scripts_handler->stage_scripts();
		return $this;
	}

	public function enqueue_immediate_scripts(): self {
		$this->scripts_handler->enqueue_immediate_scripts();
		return $this;
	}

	public function stage_styles(): self {
		$this->styles_handler->stage_styles();
		return $this;
	}

	public function enqueue_immediate_styles(): self {
		$this->styles_handler->enqueue_immediate_styles();
		return $this;
	}

	public function enqueue_media(array $media): self {
		$this->media_handler->enqueue_media($media);
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
