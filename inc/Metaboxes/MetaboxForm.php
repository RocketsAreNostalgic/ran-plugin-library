<?php
/**
 * MetaboxForm: Per-metabox FormsCore implementation.
 *
 * @package Ran\PluginLib\Metaboxes
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Metaboxes;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\FormsCore;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GenericBuilderContext;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Per-metabox form instance.
 *
 * One MetaboxForm corresponds to one `meta_key`, which is also the `main_option`.
 */
class MetaboxForm extends FormsCore implements BuilderRootInterface {
	private string $metabox_id;
	private string $title;
	/** @var array<string,mixed> */
	private array $metabox_args;
	private Metaboxes $host;
	/** @var callable */
	private $updateFn;

	/**
	 * @param RegisterOptions $options Base options instance. `main_option` must equal the metabox `meta_key`.
	 * @param ComponentManifest $components Component manifest.
	 * @param string $metabox_id Unique metabox identifier.
	 * @param string $title Metabox title.
	 * @param array<string,mixed> $args Metabox configuration.
	 * @param Metaboxes $host Parent Metaboxes manager.
	 * @param ConfigInterface|null $config Optional config.
	 * @param Logger|null $logger Optional logger.
	 */
	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
		string $metabox_id,
		string $title,
		array $args,
		Metaboxes $host,
		?ConfigInterface $config = null,
		?Logger $logger = null
	) {
		$this->logger = $logger instanceof Logger ? $logger : $options->get_logger();

		$this->base_options = $options;
		$this->main_option  = $options->get_main_option_name();
		$this->config       = $config;

		$this->components = $components;
		$this->views      = $components->get_component_loader();

		$this->metabox_id   = $metabox_id;
		$this->title        = $title;
		$this->metabox_args = $args;
		$this->host         = $host;

		$templates_dir = __DIR__ . '/templates';
		$this->views->register_absolute('metabox.root-wrapper', $templates_dir . '/metabox-wrapper.php');

		$this->form_service    = new FormsService($this->components, $this->logger);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);
		$this->field_renderer->set_message_handler($this->message_handler);

		$this->_start_form_session();
		$this->form_session->set_form_defaults(array(
			'root-wrapper' => 'metabox.root-wrapper',
		));

		$this->updateFn = $this->_create_update_function();

		$this->containers[$this->metabox_id] = array(
			'meta' => array(
				'id'    => $this->metabox_id,
				'title' => $this->title,
			),
			'children' => array(),
		);
	}

	public function get_metabox_id(): string {
		return $this->metabox_id;
	}

	public function get_title(): string {
		return $this->title;
	}

	public function get_meta_key(): string {
		return $this->main_option;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_metabox_args(): array {
		return $this->metabox_args;
	}

	public function get_nonce_action(): string {
		return $this->_get_nonce_action();
	}

	public function get_nonce_name(): string {
		return $this->_get_nonce_name();
	}

	/** @return array<string> */
	public function get_post_types(): array {
		$post_types = $this->metabox_args['post_types'] ?? array();
		if (!is_array($post_types)) {
			return array();
		}
		$post_types = array_values(array_filter(array_map('strval', $post_types), static fn (string $t): bool => trim($t) !== ''));
		return $post_types;
	}

	public function get_context(): string {
		$context = (string) ($this->metabox_args['context'] ?? 'advanced');
		$context = $context !== '' ? $context : 'advanced';
		return $context;
	}

	public function get_priority(): string {
		$priority = (string) ($this->metabox_args['priority'] ?? 'default');
		$priority = $priority !== '' ? $priority : 'default';
		return $priority;
	}

	public function boot(bool $eager = false): void {
		// No-op: Metaboxes manager registers hooks.
	}

	public function __render(string $id_slug, ?array $context = null): void {
		$post_id = (int) ($context['post_id'] ?? 0);
		if ($post_id <= 0) {
			$this->logger->warning('metabox.render.missing_post_id', array(
				'metabox_id' => $this->metabox_id,
				'meta_key'   => $this->main_option,
			));
			return;
		}

		$this->_start_form_session();
		$this->_restore_form_messages($post_id);

		$opts   = $this->base_options->with_context(StorageContext::forPost($post_id));
		$values = $opts->get_options();

		$sections = $this->sections[$this->metabox_id] ?? array();
		$sections = is_array($sections) ? $sections : array();

		$rendered_content = $this->_render_default_sections_wrapper($this->metabox_id, $sections, $values);

		$internalSchema = $this->_resolve_schema_bundle($opts, array('post_id' => $post_id));
		$schemaSummary  = $this->_build_schema_summary($internalSchema);

		$this->logger->debug('metabox.render.schema_trace', array(
			'metabox_id' => $this->metabox_id,
			'fields'     => $schemaSummary,
		));

		$base_css_url = $this->_do_apply_filter(
			'ran_plugin_lib_forms_base_stylesheet_url',
			$this->_do_plugins_url('../Forms/assets/forms.base.css', __FILE__),
			$this->metabox_id,
			$this
		);
		if ($base_css_url !== '') {
			$this->_do_wp_enqueue_style(
				'ran-plugin-lib-forms-base',
				$base_css_url,
				array(),
				'1.0.0'
			);
		}

		$payload = array(
			...($context ?? array()),
			'metabox_id'        => $this->metabox_id,
			'meta_key'          => $this->main_option,
			'title'             => $this->title,
			'options'           => $values,
			'values'            => $values,
			'inner_html'        => $rendered_content,
			'messages_by_field' => $this->message_handler->get_all_messages(),
			'nonce_action'      => $this->_get_nonce_action(),
			'nonce_name'        => $this->_get_nonce_name(),
		);

		$this->_finalize_render($this->metabox_id, $payload, array('metabox_id' => $this->metabox_id));
	}

	/**
	 * Merge file upload values into a metabox payload.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	protected function _process_file_uploads(array $payload): array {
		$processed = $this->_process_uploaded_files();
		return array_merge($payload, $processed);
	}

	/**
	 * Public wrapper for file upload processing.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function process_file_uploads(array $payload): array {
		return $this->_process_file_uploads($payload);
	}

	/**
	 * Normalize and persist metabox payload for a specific post.
	 *
	 * Message persistence is keyed by post_id.
	 *
	 * @internal WordPress save_post flow should call this via Metaboxes manager.
	 *
	 * @param array<string,mixed> $payload Posted values under the meta_key namespace.
	 * @param array<string,mixed> $context Must include post_id.
	 * @return void
	 */
	public function __save_metabox(array $payload, array $context): void {
		$post_id = isset($context['post_id']) ? (int) $context['post_id'] : 0;
		if ($post_id <= 0) {
			return;
		}

		$opts = $this->resolve_options(array(
			'post_id' => $post_id,
		));

		$this->_prepare_validation_messages($payload);

		$bundle = $this->_resolve_schema_bundle($opts, array(
			'intent'   => 'save',
			'post_id'  => $post_id,
			'metabox'  => $this->metabox_id,
			'meta_key' => $this->main_option,
		));

		// Consolidate bundle sources into single registration call
		$merged = $this->_merge_schema_bundle_sources($bundle);
		if (!empty($merged['merged_schema'])) {
			$opts->__register_internal_schema(
				$merged['merged_schema'],
				$merged['metadata'],
				$merged['queued_validators'],
				$merged['queued_sanitizers'],
				$merged['defaults_for_seeding']
			);
		}

		// Seed defaults for missing keys (register_schema handles seeding + telemetry)
		if (!empty($merged['defaults_for_seeding'])) {
			$opts->register_schema($merged['defaults_for_seeding']);
		}

		$opts->stage_options($payload);
		$messages = $this->_process_validation_messages($opts);

		if ($this->_has_validation_failures()) {
			$this->_log_validation_failure(
				'MetaboxForm::__save_metabox validation failed; aborting persistence.',
				array_merge(
					array(
						'post_id'                  => $post_id,
						'metabox_id'               => $this->metabox_id,
						'meta_key'                 => $this->main_option,
						'validation_message_count' => is_array($messages) ? count($messages) : 0,
						'validation_message_keys'  => is_array($messages) ? array_keys($messages) : array(),
					),
					ErrorNoticeRenderer::isVerboseDebug() ? array(
						'validation_messages' => $messages,
					) : array()
				)
			);

			$this->_persist_form_messages($messages, $post_id);
			return;
		}

		$opts->commit_merge();

		if (!empty($messages)) {
			$this->_persist_form_messages($messages, $post_id);
		}

		$this->_clear_pending_validation();
	}

	public function resolve_options(?array $context = null): RegisterOptions {
		return parent::resolve_options($context);
	}

	public function get_settings(): FormsInterface {
		return $this;
	}

	public function __get_forms(): FormsInterface {
		return $this;
	}

	public function end(): mixed {
		return $this->host;
	}

	public function heading(string $heading): static {
		$this->title = $heading;
		return $this;
	}

	public function description(string|callable $description): static {
		$this->metabox_args['description'] = $description;
		return $this;
	}

	public function template(string|callable|null $template_key): static {
		$this->override_form_defaults(array(
			'root-wrapper' => $template_key ?? 'metabox.root-wrapper',
		));
		return $this;
	}

	public function order(?int $order): static {
		$this->metabox_args['order'] = $order;
		return $this;
	}

	public function before(?callable $before): static {
		$this->metabox_args['before'] = $before;
		return $this;
	}

	public function after(?callable $after): static {
		$this->metabox_args['after'] = $after;
		return $this;
	}

	public function section(string $section_id, string $title, string|callable|null $description_cb = null, ?array $args = null): SectionBuilderInterface {
		$args  = $args          ?? array();
		$order = $args['order'] ?? null;

		($this->updateFn)('section', array(
			'container_id' => $this->metabox_id,
			'section_id'   => $section_id,
			'section_data' => array(
				'title'          => $title,
				'description_cb' => $description_cb,
				'order'          => ($order !== null ? (int) $order : 0),
				'before'         => $args['before'] ?? null,
				'after'          => $args['after']  ?? null,
			),
		));

		$context = new GenericBuilderContext($this, $this->metabox_id, $this->updateFn);
		return new SectionBuilder(
			$this,
			$context,
			$section_id,
			$title,
			$args['before'] ?? null,
			$args['after']  ?? null,
			($order instanceof \Closure || is_callable($order)) ? null : ($order === null ? null : (int) $order)
		);
	}

	protected function _get_form_type_suffix(): string {
		return 'metabox';
	}

	protected function _should_load(): bool {
		return $this->_do_is_admin();
	}

	protected function _handle_context_update(string $type, array $data): void {
		// Intentionally no-op.
	}

	protected function _resolve_context(array $context): array {
		$post_id = (int) ($context['post_id'] ?? 0);
		if ($post_id <= 0) {
			return array(
				'storage' => $this->base_options->get_storage_context(),
			);
		}

		return array(
			'storage' => StorageContext::forPost($post_id),
		);
	}

	private function _get_nonce_action(): string {
		return 'ran_plugin_lib_metabox|' . $this->metabox_id . '|' . $this->main_option;
	}

	private function _get_nonce_name(): string {
		return $this->main_option . '__nonce';
	}
}
