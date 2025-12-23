<?php
/**
 * Metabox Registry Metabox Builder
 *
 * Lightweight builder for metabox metadata.
 * Used by MetaboxesRegistry to define metaboxes with on_render callbacks.
 *
 * @package Ran\PluginLib\Metaboxes
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Metaboxes;

use Ran\PluginLib\Forms\Builders\DeferredBuilderTrait;

/**
 * Lightweight builder for metabox metadata.
 *
 * Collects metabox configuration (post_types, context, priority, etc.) and an
 * on_render callback. Does NOT create expensive dependencies - that happens when
 * the metabox is actually rendered on a post edit screen.
 */
class MetaboxRegistryMetaboxBuilder {
	use DeferredBuilderTrait;

	private MetaboxesRegistry $registry;
	private string $metabox_id;
	private string $title;
	private string $meta_key;

	/**
	 * @var array<string,mixed>
	 */
	private array $meta = array();

	/**
	 * @var callable|null
	 */
	private $render_callback = null;

	/**
	 * Schema for field validation/sanitization.
	 *
	 * @var array|callable|null
	 */
	private mixed $schema = null;

	/**
	 * @param MetaboxesRegistry $registry Parent registry.
	 * @param string $metabox_id Metabox id.
	 * @param string $title Metabox title.
	 * @param string $meta_key Post meta key used for storage.
	 * @param array<string,mixed> $args Metabox args.
	 */
	public function __construct(MetaboxesRegistry $registry, string $metabox_id, string $title, string $meta_key, array $args = array()) {
		$this->registry   = $registry;
		$this->metabox_id = $metabox_id;
		$this->title      = $title;
		$this->meta_key   = $meta_key;
		$this->meta       = $args;
		$this->_initDeferred($registry->get_logger());
	}

	/**
	 * Set the metabox title.
	 */
	public function title(string $title): static {
		$this->title = $title;
		return $this;
	}

	/**
	 * Set post types for this metabox.
	 *
	 * @param array<string> $post_types
	 */
	public function post_types(array $post_types): static {
		$this->meta['post_types'] = $post_types;
		return $this;
	}

	/**
	 * Set the metabox context (advanced|side|normal).
	 */
	public function context(string $context): static {
		$this->meta['context'] = $context;
		return $this;
	}

	/**
	 * Set the metabox priority (high|core|default|low).
	 */
	public function priority(string $priority): static {
		$this->meta['priority'] = $priority;
		return $this;
	}

	/**
	 * Set the validation/sanitization schema for this metabox's fields.
	 *
	 * @param array|callable $schema
	 */
	public function schema(array|callable $schema): static {
		$this->schema = $schema;
		return $this;
	}

	public function register_components(array $options): static {
		$path = isset($options['path']) ? trim((string) $options['path']) : '';
		if ($path === '') {
			return $this;
		}
		if (!isset($this->meta['register_components']) || !is_array($this->meta['register_components'])) {
			$this->meta['register_components'] = array();
		}
		$this->meta['register_components'][] = $options;
		return $this;
	}

	/**
	 * Set the render callback for defining sections and fields.
	 *
	 * @param callable $callback Receives MetaboxForm.
	 */
	public function on_render(callable $callback): static {
		$this->render_callback = $callback;
		return $this;
	}

	/**
	 * Finalize and store the metabox definition.
	 */
	public function end_metabox(): MetaboxesRegistry {
		if ($this->render_callback === null && $this->hasDeferred()) {
			$this->render_callback = $this->_createDeferredRenderCallback();
		}

		$final_callback = $this->_wrapCallbackWithSchema($this->render_callback);

		$this->registry->_store_metabox(
			$this->metabox_id,
			$this->title,
			$this->meta_key,
			$this->meta,
			$final_callback
		);

		return $this->registry;
	}

	/**
	 * Wrap a render callback to register schema before execution.
	 *
	 * @param callable|null $callback
	 * @return callable|null
	 */
	private function _wrapCallbackWithSchema(?callable $callback): ?callable {
		if ($this->schema === null || $callback === null) {
			return $callback;
		}

		$schema = $this->schema;
		return function ($builder) use ($callback, $schema) {
			$resolved_schema = is_callable($schema) ? $schema() : $schema;
			$builder->get_options()->register_schema($resolved_schema);
			$callback($builder);
		};
	}
}
