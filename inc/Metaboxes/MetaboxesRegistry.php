<?php
/**
 * Metaboxes Registry
 *
 * Lightweight registry for post edit screen metaboxes with lazy loading.
 * Parallel to AdminMenuRegistry/UserSettingsRegistry.
 *
 * @package Ran\PluginLib\Metaboxes
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Metaboxes;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Lightweight registry for metaboxes.
 *
 * Registers WordPress metabox hooks immediately but defers expensive dependency
 * creation (RegisterOptions, ComponentManifest, Metaboxes manager) until a post
 * edit screen is actually rendered.
 */
class MetaboxesRegistry {
	use WPWrappersTrait;

	private string $option_key;
	private StorageContext $storage_context;
	private Logger $logger;
	private ?ConfigInterface $config;

	private ?RegisterOptions $options      = null;
	private ?ComponentManifest $components = null;
	private ?Metaboxes $metaboxes          = null;

	/**
	 * @var array<string, array{title: string, meta_key: string, args: array<string,mixed>}>
	 */
	private array $metaboxes_meta = array();

	/**
	 * @var array<string, callable|null>
	 */
	private array $render_callbacks = array();

	/**
	 * @var array<string,bool>
	 */
	private array $render_callbacks_ran = array();

	/**
	 * @var array<string, MetaboxForm>
	 */
	private array $forms = array();

	private bool $hooks_registered = false;

	public function __construct(
		string $option_key,
		StorageContext $storage_context,
		Logger $logger,
		?ConfigInterface $config = null
	) {
		$this->option_key      = $option_key;
		$this->storage_context = $storage_context;
		$this->logger          = $logger;
		$this->config          = $config;
	}

	public function get_logger(): Logger {
		return $this->logger;
	}

	public function get_option_key(): string {
		return $this->option_key;
	}

	/**
	 * Register metaboxes with a builder callback.
	 */
	public function register(callable $callback): void {
		try {
			$callback($this);
		} catch (\Throwable $e) {
			$this->logger->error('metaboxes_registry.register_callback_error', array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));

			add_action('admin_notices', function () use ($e) {
				ErrorNoticeRenderer::render($e, 'MetaboxesRegistry Error');
			});
			return;
		}

		$this->_register_hooks();

		$this->logger->debug('metaboxes_registry.registered', array(
			'option_key' => $this->option_key,
			'metaboxes'  => array_keys($this->metaboxes_meta),
		));
	}

	public function metabox(string $metabox_id, string $title, string $meta_key, array $args = array()): MetaboxRegistryMetaboxBuilder {
		return new MetaboxRegistryMetaboxBuilder($this, $metabox_id, $title, $meta_key, $args);
	}

	/**
	 * Store metabox metadata and render callback.
	 *
	 * @internal Called by MetaboxRegistryMetaboxBuilder.
	 */
	public function _store_metabox(string $metabox_id, string $title, string $meta_key, array $meta, ?callable $callback): void {
		$this->metaboxes_meta[$metabox_id] = array(
			'title'    => $title,
			'meta_key' => $meta_key,
			'args'     => $meta,
		);
		$this->render_callbacks[$metabox_id] = $callback;
	}

	private function _register_hooks(): void {
		if ($this->hooks_registered) {
			return;
		}

		$this->_do_add_action('add_meta_boxes', array($this, '_register_metaboxes'), 10, 2);
		$this->_do_add_action('save_post', array($this, '_save_metaboxes'), 10, 3);
		$this->_do_add_action('admin_enqueue_scripts', array($this, '_preflight_enqueue_assets'), 10, 1);

		$this->hooks_registered = true;

		$this->logger->debug('metaboxes_registry.hooks_registered', array(
			'option_key' => $this->option_key,
		));
	}

	public function _preflight_enqueue_assets($hook_suffix = ''): void {
		$pagenow = $GLOBALS['pagenow'] ?? '';
		if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
			return;
		}

		$this->_ensure_metaboxes();
		if ($this->metaboxes === null || $this->components === null) {
			return;
		}

		$this->_run_all_render_callbacks();

		$aliases = array();
		foreach ($this->forms as $metabox_id => $form) {
			$ids = $form->collect_used_component_aliases($metabox_id);
			if (!empty($ids)) {
				$aliases = array_merge($aliases, $ids);
			}
		}
		$aliases = array_values(array_unique($aliases));

		$this->components->enqueue_assets_for_aliases($aliases);
	}

	public function _register_metaboxes(string $post_type, object $post): void {
		$this->_ensure_metaboxes();
		if ($this->metaboxes === null) {
			return;
		}

		$this->_run_all_render_callbacks();

		$this->metaboxes->__register_metaboxes($post_type, $post);
	}

	public function _save_metaboxes(int $post_id, object $post, bool $update): void {
		$this->_ensure_metaboxes();
		if ($this->metaboxes === null) {
			return;
		}

		$this->_run_all_render_callbacks();

		$this->metaboxes->__save_metaboxes($post_id, $post, $update);
	}

	private function _ensure_metaboxes(): void {
		if ($this->metaboxes !== null) {
			return;
		}

		$this->options = new RegisterOptions(
			$this->option_key,
			$this->storage_context,
			false,
			$this->logger
		);

		$componentDir = new ComponentLoader(
			dirname(__DIR__) . '/Forms/Components',
			$this->logger
		);

		if ($this->config !== null) {
			$registrations = array();
			foreach ($this->metaboxes_meta as $meta) {
				$register_components = $meta['args']['register_components'] ?? array();
				if (!is_array($register_components)) {
					continue;
				}
				foreach ($register_components as $options) {
					if (!is_array($options)) {
						continue;
					}
					$registrations[] = $options;
				}
			}
			$componentDir->register_components_batch($registrations, $this->config);
		}

		$this->components = new ComponentManifest($componentDir, $this->logger);

		$this->metaboxes = new Metaboxes(
			$this->options,
			$this->components,
			$this->config,
			$this->logger
		);
	}

	private function _run_all_render_callbacks(): void {
		if ($this->metaboxes === null) {
			return;
		}

		foreach ($this->metaboxes_meta as $metabox_id => $meta) {
			if (($this->render_callbacks_ran[$metabox_id] ?? false) === true) {
				continue;
			}

			$callback = $this->render_callbacks[$metabox_id] ?? null;

			$title    = (string) ($meta['title'] ?? '');
			$meta_key = (string) ($meta['meta_key'] ?? '');
			$args     = $meta['args'] ?? array();
			$args     = is_array($args) ? $args : array();

			$form = $this->forms[$metabox_id] ?? null;
			if (!$form instanceof MetaboxForm) {
				$form                     = $this->metaboxes->metabox($metabox_id, $title, $meta_key, $args);
				$this->forms[$metabox_id] = $form;
			}

			if ($callback !== null) {
				try {
					$callback($form);
				} catch (\Throwable $e) {
					$this->logger->error('metaboxes_registry.render_callback_error', array(
						'metabox' => $metabox_id,
						'message' => $e->getMessage(),
						'file'    => $e->getFile(),
						'line'    => $e->getLine(),
					));
					ErrorNoticeRenderer::renderWithContext($e, 'Metabox Error', 'metabox', $metabox_id);
				}
			}

			$this->render_callbacks_ran[$metabox_id] = true;
		}
	}
}
