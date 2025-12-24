<?php
/**
 * Metaboxes: DX-friendly bridge to WordPress metabox APIs using RegisterOptions.
 *
 * @package Ran\PluginLib\Metaboxes
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Metaboxes;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Metabox manager.
 *
 * This class coordinates metabox registration and save handling, delegating
 * per-metabox render logic to MetaboxForm instances.
 */
class Metaboxes implements FormsInterface {
	use WPWrappersTrait;

	/** @var array<string, MetaboxForm> */
	private array $metaboxes = array();
	private bool $booted     = false;

	private RegisterOptions $base_options;
	private ComponentManifest $components;
	private ?ConfigInterface $config;
	private Logger $logger;

	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
		?ConfigInterface $config = null,
		?Logger $logger = null
	) {
		$this->logger       = $logger instanceof Logger ? $logger : $options->get_logger();
		$this->base_options = $options;
		$this->components   = $components;
		$this->config       = $config;
	}

	/**
	 * Define a new metabox.
	 *
	 * $args must include:
	 * - post_types: array<string>
	 *
	 * Optional:
	 * - context: string (advanced|side|normal)
	 * - priority: string (high|core|default|low)
	 *
	 * @param string $metabox_id Unique metabox identifier.
	 * @param string $title Metabox title.
	 * @param string $meta_key Post meta key used for grouped storage.
	 * @param array<string,mixed> $args Configuration.
	 * @return MetaboxForm
	 */
	public function metabox(string $metabox_id, string $title, string $meta_key, array $args = array()): MetaboxForm {
		$metabox_id = trim($metabox_id);
		$meta_key   = trim($meta_key);
		if ($metabox_id === '') {
			throw new \InvalidArgumentException('Metaboxes::metabox requires a non-empty metabox_id.');
		}
		if ($meta_key === '') {
			throw new \InvalidArgumentException('Metaboxes::metabox requires a non-empty meta_key.');
		}
		if (!isset($args['post_types']) || !is_array($args['post_types']) || $args['post_types'] === array()) {
			throw new \InvalidArgumentException('Metaboxes::metabox requires args[post_types] as a non-empty array.');
		}

		// Create a per-metabox RegisterOptions instance keyed by meta_key.
		// Context will be resolved at render/save time using StorageContext::forPost(post_id).
		$opts = new RegisterOptions(
			$meta_key,
			$this->base_options->get_storage_context(),
			false,
			$this->logger
		);
		$opts->__set_validator_pipeline($this->base_options->get_validator_pipeline());
		$opts->with_policy($this->base_options->get_write_policy());
		$opts->__set_schema_internal($this->base_options->__get_schema_internal());

		$form = new MetaboxForm(
			$opts,
			$this->components,
			$metabox_id,
			$title,
			$args,
			$this,
			$this->config,
			$this->logger
		);

		$this->metaboxes[$metabox_id] = $form;
		return $form;
	}

	public function boot(bool $eager = false): void {
		if ($this->booted) {
			return;
		}
		$this->booted = true;

		$hooks = array(
			array(
				'hook'          => 'add_meta_boxes',
				'callback'      => array($this, '__register_metaboxes'),
				'priority'      => 10,
				'accepted_args' => 2,
			),
			array(
				'hook'          => 'save_post',
				'callback'      => array($this, '__save_metaboxes'),
				'priority'      => 10,
				'accepted_args' => 3,
			),
		);

		foreach ($hooks as $definition) {
			if (!isset($definition['hook'], $definition['callback'])) {
				continue;
			}
			$priority      = isset($definition['priority']) ? (int) $definition['priority'] : 10;
			$accepted_args = isset($definition['accepted_args']) ? (int) $definition['accepted_args'] : 1;
			$this->_do_add_action(
				(string) $definition['hook'],
				$definition['callback'],
				$priority,
				$accepted_args
			);
		}
	}

	public function safe_boot(callable $callback, bool $eager = false): void {
		try {
			$callback($this);
			$this->boot($eager);
		} catch (\Throwable $e) {
			$this->logger->error('metaboxes.safe_boot.error', array(
				'class'   => static::class,
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));
			return;
		}
	}

	public function __render(string $id_slug, ?array $context = null): void {
		$form = $this->metaboxes[$id_slug] ?? null;
		if (!$form instanceof MetaboxForm) {
			return;
		}
		$form->__render($id_slug, $context);
	}

	public function resolve_options(?array $context = null): RegisterOptions {
		return $this->base_options;
	}

	public function get_value(string $field_id, mixed $default = null, ?array $context = null): mixed {
		$context = $context ?? array();

		$metabox_id = '';
		if (isset($context['metabox_id'])) {
			$metabox_id = (string) $context['metabox_id'];
		} elseif (isset($context['id_slug'])) {
			$metabox_id = (string) $context['id_slug'];
		}

		if ($metabox_id === '') {
			throw new \InvalidArgumentException('Metaboxes::get_value requires context[metabox_id].');
		}

		$form = $this->metaboxes[$metabox_id] ?? null;
		if (!$form instanceof MetaboxForm) {
			throw new \InvalidArgumentException('Metaboxes::get_value unknown metabox_id: ' . $metabox_id);
		}

		return $form->get_value($field_id, $default, $context);
	}

	public function get_values(?array $context = null): array {
		$context = $context ?? array();

		$metabox_id = '';
		if (isset($context['metabox_id'])) {
			$metabox_id = (string) $context['metabox_id'];
		} elseif (isset($context['id_slug'])) {
			$metabox_id = (string) $context['id_slug'];
		}

		if ($metabox_id === '') {
			throw new \InvalidArgumentException('Metaboxes::get_values requires context[metabox_id].');
		}

		$form = $this->metaboxes[$metabox_id] ?? null;
		if (!$form instanceof MetaboxForm) {
			throw new \InvalidArgumentException('Metaboxes::get_values unknown metabox_id: ' . $metabox_id);
		}

		return $form->get_values($context);
	}

	public function override_form_defaults(array $overrides): void {
		// Not supported on the manager.
	}

	public function get_form_session(): ?FormsServiceSession {
		return null;
	}

	public function register_component(string $name, array $options): static {
		return $this;
	}

	public function register_components(array $options): static {
		return $this;
	}

	/**
	 * WordPress callback for add_meta_boxes.
	 *
	 * @param string $post_type
	 * @param object $post
	 */
	public function __register_metaboxes(string $post_type, object $post): void {
		foreach ($this->metaboxes as $metabox_id => $form) {
			$post_types = $form->get_post_types();
			if (!in_array($post_type, $post_types, true)) {
				continue;
			}
			$this->_do_add_meta_box(
				$metabox_id,
				$form->get_title(),
				function ($p, array $box) use ($metabox_id, $form): void {
					$post_id = 0;
					if (is_object($p) && isset($p->ID)) {
						$post_id = (int) $p->ID;
					}
					$form->__render($metabox_id, array(
						'post_id' => $post_id,
						'post'    => $p,
						'box'     => $box,
					));
				},
				$post_type,
				$form->get_context(),
				$form->get_priority(),
				array()
			);
		}
	}

	/**
	 * WordPress callback for save_post.
	 *
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update
	 */
	public function __save_metaboxes(int $post_id, object $post, bool $update): void {
		if ($post_id <= 0) {
			return;
		}

		// Bail out early for autosaves and revisions.
		if ($this->_do_wp_is_post_autosave($post_id) !== false) {
			return;
		}
		if ($this->_do_wp_is_post_revision($post_id) !== false) {
			return;
		}

		// Capability gate for the post being saved.
		if (!$this->_do_current_user_can('edit_post', $post_id)) {
			return;
		}

		$post_type = '';
		if (isset($post->post_type)) {
			$post_type = (string) $post->post_type;
		}

		foreach ($this->metaboxes as $metabox_id => $form) {
			$post_types = $form->get_post_types();
			if ($post_type !== '' && !in_array($post_type, $post_types, true)) {
				continue;
			}

			$meta_key = $form->get_meta_key();
			if ($meta_key === '') {
				continue;
			}

			$has_post_payload  = isset($_POST[$meta_key])  && is_array($_POST[$meta_key]);
			$has_files_payload = isset($_FILES[$meta_key]) && is_array($_FILES[$meta_key]);
			if (!$has_post_payload && !$has_files_payload) {
				continue;
			}
			$payload = $has_post_payload ? $_POST[$meta_key] : array();

			// Verify nonce for this metabox.
			$nonce_name   = $form->get_nonce_name();
			$nonce_action = $form->get_nonce_action();
			if ($nonce_name === '' || $nonce_action === '') {
				continue;
			}
			if (!isset($_POST[$nonce_name])) {
				continue;
			}
			$nonce = (string) $_POST[$nonce_name];
			if ($nonce === '') {
				continue;
			}
			if ($this->_do_wp_verify_nonce($nonce, $nonce_action) === false) {
				continue;
			}

			// Merge file uploads for this metabox namespace.
			$payload = $form->process_file_uploads($payload);

			$form->__save_metabox($payload, array(
				'post_id'    => $post_id,
				'post'       => $post,
				'update'     => $update,
				'post_type'  => $post_type,
				'metabox_id' => $metabox_id,
				'meta_key'   => $meta_key,
			));
		}
	}
}
