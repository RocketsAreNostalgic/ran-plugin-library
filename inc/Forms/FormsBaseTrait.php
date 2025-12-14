<?php
/**
 * FormsBaseTrait: Shared functionality for form-based classes.
 *
 * @package Ran\PluginLib\Forms
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use UnexpectedValueException;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Forms\Services\FormsValidatorServiceInterface;
use Ran\PluginLib\Forms\Services\FormsValidatorService;
use Ran\PluginLib\Forms\Services\FormsUpdateRouterInterface;
use Ran\PluginLib\Forms\Services\FormsUpdateRouter;
use Ran\PluginLib\Forms\Services\FormsStateStoreInterface;
use Ran\PluginLib\Forms\Services\FormsStateStore;
use Ran\PluginLib\Forms\Services\FormsSchemaServiceInterface;
use Ran\PluginLib\Forms\Services\FormsSchemaService;
use Ran\PluginLib\Forms\Services\FormsRenderServiceInterface;
use Ran\PluginLib\Forms\Services\FormsRenderService;
use Ran\PluginLib\Forms\Services\FormsMessageServiceInterface;
use Ran\PluginLib\Forms\Services\FormsMessageService;
use Ran\PluginLib\Forms\Services\FormsFileUploadServiceInterface;
use Ran\PluginLib\Forms\Services\FormsFileUploadService;
use Ran\PluginLib\Forms\Services\FormsErrorHandlerInterface;
use Ran\PluginLib\Forms\Services\DefaultFormsErrorHandler;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Shared functionality for form-based classes.
 *
 * This trait provides shared infrastructure for AdminSettings, UserSettings, and future FrontendForms.
 *
 * Organization:
 * 1. Properties (protected public contract, private internal state)
 * 2. Abstract Methods (contract for implementers)
 * 3. Public API (service access, template setters, messages)
 * 4. Protected Methods by responsibility:
 *    - Validation Message Helpers
 *    - Session & Hook Management
 *    - Builder Update Handlers
 *    - Rendering Helpers
 *    - Validator/Sanitizer Injection
 *    - Schema Bundle Resolution
 */
trait FormsBaseTrait {
	// =========================================================================
	// PROPERTIES
	// =========================================================================

	// -- Protected: Public contract --

	protected string $main_option;
	protected ?array $pending_values = null;
	protected ComponentManifest $components;
	protected ComponentLoader $views;
	protected ?ConfigInterface $config = null;
	protected FormsService $form_service;
	protected FormElementRenderer $field_renderer;
	protected FormMessageHandler $message_handler;
	protected ?FormsServiceSession $form_session = null;
	protected ?FormsAssets $shared_assets        = null;
	protected Logger $logger;
	protected RegisterOptions $base_options;

	// Settings structure: containers, sections, fields, and groups organized by container

	/** @var array<string, array{meta:array, children:array, lookup?:array}> */
	protected array $containers = array();
	/** @var array<string, array<string, array{title:string, description_cb:string|callable|null, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $sections = array();
	/** @var array<string, array<string, array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int, before:?callable, after:?callable}>>> */
	protected array $fields = array();
	/** @var array<string, array<string, array{group_id:string, fields:array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $groups = array();
	/** @var array<string, array{zone_id:string, before:?callable, after:?callable, controls: array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int}>}> */
	protected array $submit_controls = array();

	// -- Private: Internal state

	/** @var array<string, array<int, callable>> */
	private array $__queued_component_validators = array();

	/** @var array<string, array<int, callable>> */
	private array $__queued_component_sanitizers = array();

	/** @var array<string, array<string,mixed>> */
	private array $__schema_bundle_cache = array();

	/** @var array<string, array<string,mixed>>|null Session-scoped catalogue cache for defaults memoization */
	private ?array $__catalogue_cache = null;

	/** @var array<string, RegisterOptions> Cache of resolved RegisterOptions by storage context key */
	private array $__resolved_options_cache = array();

	private ?FormsStateStoreInterface $__state_store                = null;
	private ?FormsUpdateRouterInterface $__update_router            = null;
	private ?FormsErrorHandlerInterface $__error_handler            = null;
	private ?FormsValidatorServiceInterface $__validator_service    = null;
	private ?FormsSchemaServiceInterface $__schema_service          = null;
	private ?FormsMessageServiceInterface $__message_service        = null;
	private ?FormsRenderServiceInterface $__render_service          = null;
	private ?FormsFileUploadServiceInterface $__file_upload_service = null;

	// Template override system removed - now handled by FormsTemplateOverrideResolver in FormsServiceSession

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;

	protected function _get_state_store(): FormsStateStoreInterface {
		if ($this->__state_store instanceof FormsStateStoreInterface) {
			return $this->__state_store;
		}

		$this->__state_store = new FormsStateStore($this->containers, $this->sections, $this->fields, $this->groups, $this->submit_controls);
		return $this->__state_store;
	}

	protected function _get_update_router(): FormsUpdateRouterInterface {
		if ($this->__update_router instanceof FormsUpdateRouterInterface) {
			return $this->__update_router;
		}

		$this->__update_router = new FormsUpdateRouter();
		return $this->__update_router;
	}

	protected function _get_error_handler(): FormsErrorHandlerInterface {
		if ($this->__error_handler instanceof FormsErrorHandlerInterface) {
			return $this->__error_handler;
		}

		$this->__error_handler = new DefaultFormsErrorHandler();
		return $this->__error_handler;
	}

	protected function _get_validator_service(): FormsValidatorServiceInterface {
		if ($this->__validator_service instanceof FormsValidatorServiceInterface) {
			return $this->__validator_service;
		}

		$queued_validators         = & $this->__queued_component_validators;
		$queued_sanitizers         = & $this->__queued_component_sanitizers;
		$this->__validator_service = new FormsValidatorService(
			$this->base_options,
			$this->components,
			$this->logger,
			$queued_validators,
			$queued_sanitizers
		);
		return $this->__validator_service;
	}

	protected function _get_schema_service(): FormsSchemaServiceInterface {
		if ($this->__schema_service instanceof FormsSchemaServiceInterface) {
			return $this->__schema_service;
		}

		$schema_bundle_cache    = & $this->__schema_bundle_cache;
		$catalogue_cache        = & $this->__catalogue_cache;
		$this->__schema_service = new FormsSchemaService(
			$this->base_options,
			$this->components,
			$this->logger,
			$this->_get_validator_service(),
			static::class,
			$schema_bundle_cache,
			$catalogue_cache,
			fn (): ?FormsServiceSession => $this->get_form_session(),
			function (): void {
				$this->_start_form_session();
			},
			fn (): array => $this->_get_registered_field_metadata()
		);

		return $this->__schema_service;
	}

	protected function _get_message_service(): FormsMessageServiceInterface {
		if ($this->__message_service instanceof FormsMessageServiceInterface) {
			return $this->__message_service;
		}

		$pending_values          = & $this->pending_values;
		$this->__message_service = new FormsMessageService(
			$this->message_handler,
			$this->logger,
			$this->main_option,
			$pending_values,
			fn (string $key): string                        => $this->_do_sanitize_key($key),
			fn (): int                                      => (int) $this->_do_get_current_user_id(),
			fn (string $key, mixed $value, int $ttl): mixed => $this->_do_set_transient($key, $value, $ttl),
			fn (string $key): mixed                         => $this->_do_get_transient($key),
			fn (string $key): mixed                         => $this->_do_delete_transient($key),
			fn (): string                                   => $this->_get_form_type_suffix()
		);

		return $this->__message_service;
	}

	protected function _get_render_service(): FormsRenderServiceInterface {
		if ($this->__render_service instanceof FormsRenderServiceInterface) {
			return $this->__render_service;
		}

		$this->__render_service = new FormsRenderService(
			$this->_get_state_store(),
			$this->logger,
			$this->views,
			$this->field_renderer,
			$this->main_option,
			function (): void {
				$this->_start_form_session();
			},
			fn (): ?FormsServiceSession => $this->get_form_session(),
			fn (): string               => $this->_get_section_template()
		);

		return $this->__render_service;
	}

	protected function _get_file_upload_service(): FormsFileUploadServiceInterface {
		if ($this->__file_upload_service instanceof FormsFileUploadServiceInterface) {
			return $this->__file_upload_service;
		}

		$this->__file_upload_service = new FormsFileUploadService(
			$this->logger,
			$this->main_option,
			fn (string $path): bool                                                                     => \is_uploaded_file($path),
			fn (array $file, array $overrides = array(), string $time = ''): array                      => $this->_do_wp_handle_upload($file, $overrides, $time),
			fn (string $filename): string                                                               => $this->_do_sanitize_file_name($filename),
			fn (array $args, string $file = '', int $parent = 0, bool $wp_error = false): int|\WP_Error => $this->_do_wp_insert_attachment($args, $file, $parent, $wp_error),
			fn (mixed $thing): bool                                                                     => $this->_do_is_wp_error($thing),
			fn (int $attachment_id, string $file): array                                                => $this->_do_wp_generate_attachment_metadata($attachment_id, $file),
			fn (int $attachment_id, array $data): int|false                                             => $this->_do_wp_update_attachment_metadata($attachment_id, $data),
			function (): void {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
		);

		return $this->__file_upload_service;
	}

	// =========================================================================
	// ABSTRACT METHODS (Contract for implementers)
	// =========================================================================

	/**
	 * Boot admin: register root, sections, fields, templates.
	 */
	abstract public function boot(): void;

	/**
	 * Render a registered root template.
	 *
	 * @param string $id_slug The root identifier
	 * @param array|null $context Optional context
	 */
	abstract public function __render(string $id_slug, ?array $context = null): void;

	/**
	 * Handle context update (eg AdminSettings page, UserSettings collection, etc) from builders.
	 *
	 * @param string $type The type of update
	 * @param array $data context data to update
	 * @return void
	 */
	abstract protected function _handle_context_update(string $type, array $data): void;

	/**
	 * Sanitize a key for WordPress usage.
	 * This method should be implemented by classes that use WPWrappersTrait.
	 *
	 * @param string $key
	 * @return string
	 */
	abstract protected function _do_sanitize_key(string $key): string;

	/**
	 * Resolve context for the specific implementation.
	 * Each class resolves context differently based on their scope.
	 *
	 * @param array<string,mixed> $context
	 * @return array
	 */
	abstract protected function _resolve_context(array $context): array;

	// =========================================================================
	// PUBLIC API
	// =========================================================================

	// -- Safe Execution --

	/**
	 * Execute a builder callback with error protection.
	 *
	 * !! Important: Type-hint the callback parameter for IDE autocomplete:
	 * `function(AdminSettings $s)` or `function(UserSettings $s)`
	 *
	 * Wraps the callback in a try-catch to prevent builder errors from crashing the site.
	 * On error, logs the exception and displays an admin notice in dev mode.
	 * Automatically calls boot() after the callback completes successfully.
	 *
	 * Usage:
	 * ```php
	 * // AdminSettings example:
	 * $settings->safe_boot(function(AdminSettings $s) {
	 *     $s->settings_page('my-page')
	 *         ->section('my-section', 'My Section')
	 *             ->field('my_field', 'My Field', 'fields.input')
	 *         ->end_section()
	 *     ->end_page();
	 * });
	 *
	 * // UserSettings example:
	 * $settings->safe_boot(function(UserSettings $s) {
	 *     $s->collection('profile')
	 *         ->section('my-section', 'My Section')
	 *             ->field('my_field', 'My Field', 'fields.input')
	 *         ->end_section()
	 *     ->end_collection();
	 * });
	 * ```
	 *
	 * @param callable $callback The builder callback, receives $this as argument.
	 * @param bool $eager If true, skip lazy loading checks and always register hooks.
	 * @return void
	 */
	public function safe_boot(callable $callback, bool $eager = false): void {
		try {
			// Early bail: check if we should load at all BEFORE running the callback
			if (!$eager && !$this->_should_load()) {
				$this->logger->debug('forms_base.safe_boot.skipped_early', array(
					'class'          => static::class,
					'reason'         => 'should_load_returned_false',
					'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
				));
				return;
			}

			$this->logger->debug('forms_base.safe_boot.entry', array(
				'class'          => static::class,
				'eager'          => $eager,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			));
			$callback($this); // Type-hint the callback parameter for IDE autocomplete.
			$this->logger->debug('forms_base.safe_boot.callback_complete', array(
				'class'          => static::class,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			));
			$this->boot($eager);
			$this->logger->debug('forms_base.safe_boot.boot_complete', array(
				'class'          => static::class,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			));
		} catch (\Throwable $e) {
			$this->logger->error('forms_base.safe_boot.error', array(
				'class'   => static::class,
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));
			$this->__handle_builder_error($e, 'safe_boot');
		}
	}

	/**
	 * Check if this settings instance should load at all.
	 *
	 * Override in concrete classes to implement context-specific checks.
	 * Called BEFORE the builder callback runs to avoid unnecessary work.
	 *
	 * @return bool True if should load, false to skip entirely.
	 */
	protected function _should_load(): bool {
		// Default: always load. Concrete classes override this.
		return true;
	}

	// -- Form Defaults --

	/**
	 * Override specific form-wide defaults for current context.
	 * Allows developers to customize specific templates without replacing all defaults.
	 *
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function override_form_defaults(array $overrides): void {
		if ($this->form_session === null) {
			$this->_start_form_session();
		}
		$this->form_session->override_form_defaults($overrides);
	}

	// -- Template Setters --

	/**
	 * Set the root template for current context.
	 *
	 * @param string $template_key The template key.
	 * @return void
	 */
	public function set_root_template(string $template_key): void {
		$this->_set_form_default_template('root-wrapper', $template_key);
	}

	/**
	 * Set the section template for current context.
	 *
	 * @param string $template_key The template key.
	 * @return void
	 */
	public function set_section_template(string $template_key): void {
		$this->_set_form_default_template('section-wrapper', $template_key);
	}

	/**
	 * Set the group template for current context.
	 *
	 * @param string $template_key The template key.
	 * @return void
	 */
	public function set_group_template(string $template_key): void {
		$this->_set_form_default_template('group-wrapper', $template_key);
	}

	/**
	 * Set the field template for current context.
	 *
	 * @param string $template_key The template key.
	 * @return void
	 */
	public function set_field_template(string $template_key): void {
		$this->_set_form_default_template('field-wrapper', $template_key);
	}

	/**
	 * Get the component manifest.
	 *
	 * @return ComponentManifest The component manifest.
	 */
	public function get_component_manifest(): ComponentManifest {
		return $this->components;
	}

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current admin context.
	 * Callers can chain fluent API on the returned object.
	 *
	 * Cache rationale: In a typical page lifecycle, resolve_options() is called once
	 * per request (either render OR save, not both). The cache provides minimal benefit
	 * in this flow since each request creates a fresh Settings instance with an empty
	 * cache. However, the cache helps in edge cases:
	 * - Multiple collections rendered in the same request with identical storage context
	 * - Custom code calling resolve_options() multiple times within a single operation
	 * - Future use cases where the same Settings instance handles multiple operations
	 *
	 * The overhead is minimal (array key check), so we retain it for defensive purposes.
	 *
	 * @param array<string,mixed>|null $context Optional resolution context.
	 *
	 * @return RegisterOptions
	 */
	public function resolve_options(?array $context = null): RegisterOptions {
		$resolved = $this->_resolve_context($context ?? array());
		$cacheKey = $resolved['storage']->get_cache_key();

		// Return cached instance if available (see docblock for cache rationale)
		if (isset($this->__resolved_options_cache[$cacheKey])) {
			return $this->__resolved_options_cache[$cacheKey];
		}

		// NOTE: We intentionally call with_context() even when contexts match.
		// with_context() shares the schema via __register_internal_schema(), which
		// is necessary for proper validator/sanitizer execution during stage_options().
		// Returning base_options directly would skip this schema sharing step.

		$opts                                      = $this->base_options->with_context($resolved['storage']);
		$this->__resolved_options_cache[$cacheKey] = $opts;

		return $opts;
	}

	// -- Messages --

	/**
	 * Retrieve structured validation messages captured during the most recent operation.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function take_messages(): array {
		return $this->_get_message_service()->take_messages();
	}

	/**
	 * Get the FormsServiceSession instance for direct access to template resolution.
	 *
	 * @return FormsServiceSession|null The FormsServiceSession instance or null if not started
	 */
	public function get_form_session(): ?FormsServiceSession {
		return $this->form_session;
	}

	// -- Component Registration --

	/**
	 * Register a single external component.
	 *
	 * Delegates to ComponentLoader and triggers discovery in ComponentManifest.
	 *
	 * @param string $name Component name (e.g., 'color-picker')
	 * @param array{path: string, prefix?: string} $options Component options
	 * @return static For fluent chaining
	 */
	public function register_component(string $name, array $options): static {
		if ($this->config === null) {
			$this->logger->warning("Cannot register external component '$name' without Config");
			return $this;
		}

		$this->views->register_component($name, $options, $this->config);

		// Trigger discovery for the newly registered component
		$alias = isset($options['prefix']) ? $options['prefix'] . '.' . $name : $name;
		$this->components->discover_alias($alias);

		return $this;
	}

	/**
	 * Register multiple external components from a directory.
	 *
	 * Delegates to ComponentLoader and triggers discovery for all new components.
	 *
	 * @param array{path: string, prefix?: string} $options Batch options
	 * @return static For fluent chaining
	 */
	public function register_components(array $options): static {
		if ($this->config === null) {
			$this->logger->warning('Cannot register external components without Config');
			return $this;
		}

		// Capture aliases before registration
		$before = array_keys($this->views->aliases());

		$this->views->register_components($options, $this->config);

		// Discover all newly added aliases
		$after = array_keys($this->views->aliases());
		foreach (array_diff($after, $before) as $alias) {
			$this->components->discover_alias($alias);
		}

		return $this;
	}

	// =========================================================================
	// PROTECTED METHODS
	// =========================================================================

	// -- Render Helpers --

	/**
	 * Build a schema summary for debug logging.
	 *
	 * Extracts sanitizer/validator counts and default presence from the internal schema.
	 *
	 * Note: Remaining in trait for now, untill we have a diagnostics service to handle this.
	 *
	 * @param array<string,mixed> $internalSchema The resolved schema bundle.
	 * @return array<string,array{sanitize_component_count:int,sanitize_schema_count:int,validate_component_count:int,validate_schema_count:int,has_default:bool}>
	 */
	protected function _build_schema_summary(array $internalSchema): array {
		$summary = array();
		foreach ($internalSchema as $key => $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$sanitizeComponentCount = 0;
			$sanitizeSchemaCount    = 0;
			$validateComponentCount = 0;
			$validateSchemaCount    = 0;
			if (isset($entry['sanitize']) && is_array($entry['sanitize'])) {
				$componentBucket = $entry['sanitize'][ValidatorPipelineService::BUCKET_COMPONENT] ?? null;
				$schemaBucket    = $entry['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA]    ?? null;
				if (is_array($componentBucket)) {
					$sanitizeComponentCount = count($componentBucket);
				}
				if (is_array($schemaBucket)) {
					$sanitizeSchemaCount = count($schemaBucket);
				}
			}
			if (isset($entry['validate']) && is_array($entry['validate'])) {
				$componentBucket = $entry['validate'][ValidatorPipelineService::BUCKET_COMPONENT] ?? null;
				$schemaBucket    = $entry['validate'][ValidatorPipelineService::BUCKET_SCHEMA]    ?? null;
				if (is_array($componentBucket)) {
					$validateComponentCount = count($componentBucket);
				}
				if (is_array($schemaBucket)) {
					$validateSchemaCount = count($schemaBucket);
				}
			}
			$summary[$key] = array(
				'sanitize_component_count' => $sanitizeComponentCount,
				'sanitize_schema_count'    => $sanitizeSchemaCount,
				'validate_component_count' => $validateComponentCount,
				'validate_schema_count'    => $validateSchemaCount,
				'has_default'              => array_key_exists('default', $entry),
			);
		}
		return $summary;
	}

	/**
	 * Finalize render by invoking template callback or default element, then enqueue assets.
	 *
	 * @param string $container_id The container ID (page slug or collection ID).
	 * @param array<string,mixed> $payload The render payload.
	 * @param array<string,mixed> $element_context Additional context for default element rendering.
	 * @return void
	 */
	protected function _finalize_render(string $container_id, array $payload, array $element_context = array()): void {
		$this->_get_render_service()->finalize_render($container_id, $payload, $element_context);
	}

	// -- Field/Group Utilities --

	/**
	 * Export a normalized list of field metadata captured via builder updates.
	 *
	 * Each entry includes the container/section identifiers, optional group details,
	 * and the raw field definition that was persisted by the fluent builders. This is
	 * leveraged by schema-derivation helpers so they can operate on the canonical
	 * field store without duplicating state.
	 *
	 * @return array<int, array{
	 *     container_id:string,
	 *     section_id:string,
	 *     group_id:?string,
	 *     field:array<string,mixed>,
	 *     group?:array<string,mixed>
	 * }>
	 */
	protected function _get_registered_field_metadata(): array {
		return $this->_get_state_store()->get_registered_field_metadata();
	}

	/**
	 * Lookup the registered component alias for a given field identifier.
	 *
	 * @internal
	 *
	 * @param string $field_id
	 * @return string|null
	 */
	protected function _lookup_component_alias(string $field_id): ?string {
		return $this->_get_state_store()->lookup_component_alias($field_id);
	}

	// -- Validation Message Helpers --

	/**
	 * Prepare the message handler for a new validation run.
	 *
	 * @param array<string,mixed> $payload
	 * @return void
	 */
	protected function _prepare_validation_messages(array $payload): void {
		$this->_get_message_service()->prepare_validation_messages($payload);
	}

	/**
	 * Capture validation messages emitted by the provided RegisterOptions instance.
	 *
	 * Also updates pending_values with the sanitized values from the RegisterOptions instance.
	 * This ensures that when validation fails, the user sees the sanitized values (what they
	 * would get if validation passed), not the original pre-sanitized values.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	protected function _process_validation_messages(RegisterOptions $options): array {
		return $this->_get_message_service()->process_validation_messages($options);
	}

	/**
	 * Determine whether validation failures were recorded during the current operation.
	 */
	protected function _has_validation_failures(): bool {
		return $this->_get_message_service()->has_validation_failures();
	}

	/**
	 * Clear pending validation state after a successful operation.
	 */
	protected function _clear_pending_validation(): void {
		$this->_get_message_service()->clear_pending_validation();
	}

	/**
	 * Log a validation failure with consistent warning metadata.
	 *
	 * @param string $message
	 * @param array<string,mixed> $context
	 * @param string $level
	 * @return void
	 */
	protected function _log_validation_failure(string $message, array $context = array(), string $level = 'info'): void {
		$this->_get_message_service()->log_validation_failure($message, $context, $level);
	}

	/**
	 * Log a validation success message at the desired verbosity.
	 *
	 * @param string $message
	 * @param array<string,mixed> $context
	 * @param string $level
	 * @return void
	 */
	protected function _log_validation_success(string $message, array $context = array(), string $level = 'debug'): void {
		$this->_get_message_service()->log_validation_success($message, $context, $level);
	}

	// -- Form Message Persistence --

	/**
	 * Get the transient key for persisting form messages across redirects.
	 *
	 * Uses a namespaced key to avoid collision with WordPress's settings_errors
	 * or other plugins. Includes user_id for user-scoped isolation.
	 *
	 * @param int|null $user_id Optional user ID. Defaults to current user.
	 * @return string Transient key in format: ran_form_messages_{main_option}_{user_id}
	 */
	protected function _get_form_messages_transient_key(?int $user_id = null): string {
		return $this->_get_message_service()->get_form_messages_transient_key($user_id);
	}

	/**
	 * Get the form type suffix for transient key namespacing.
	 *
	 * Override in subclasses to provide a unique suffix for each form type.
	 * This prevents AdminSettings, UserSettings, and FrontendForms from
	 * accidentally consuming each other's persisted messages.
	 *
	 * @return string Form type identifier (e.g., 'admin', 'user', 'frontend')
	 */
	protected function _get_form_type_suffix(): string {
		$class = static::class;
		if (str_contains($class, 'UserSettings')) {
			return 'user';
		}
		if (str_contains($class, 'FrontendForms') || str_contains($class, 'Frontend')) {
			return 'frontend';
		}
		return 'admin';
	}

	/**
	 * Persist form validation messages to a transient for display after redirect.
	 *
	 * This provides a reliable, WordPress-independent mechanism for persisting
	 * validation messages across the POST/redirect/GET cycle. Works consistently
	 * for AdminSettings, UserSettings, and future FrontendForms.
	 *
	 * @param array<string, array{warnings: array<int, string>, notices: array<int, string>}> $messages
	 * @param int|null $user_id Optional user ID for the transient key. Defaults to current user.
	 * @return void
	 */
	protected function _persist_form_messages(array $messages, ?int $user_id = null): void {
		$this->_get_message_service()->persist_form_messages($messages, $user_id);
	}

	/**
	 * Restore form validation messages from transient into message_handler.
	 *
	 * Reads messages persisted by _persist_form_messages() and feeds them
	 * into the FormMessageHandler for field-level display. Deletes the
	 * transient after reading for one-time display.
	 *
	 * @param int|null $user_id Optional user ID for the transient key. Defaults to current user.
	 * @return bool True if messages were restored, false if none found.
	 */
	protected function _restore_form_messages(?int $user_id = null): bool {
		return $this->_get_message_service()->restore_form_messages($user_id);
	}

	// -- Session & Hook Management --

	/**
	 * Register a batch of WordPress action hooks using the wrappers provided by the host class.
	 *
	 * @param array<int, array{hook:string, callback:callable, priority?:int, accepted_args?:int}> $hooks
	 * @return void
	 */
	protected function _register_action_hooks(array $hooks): void {
		foreach ($hooks as $definition) {
			if (!isset($definition['hook'], $definition['callback'])) {
				continue;
			}
			$priority      = isset($definition['priority']) ? (int) $definition['priority'] : 10;
			$accepted_args = isset($definition['accepted_args']) ? (int) $definition['accepted_args'] : 1;
			$this->_do_add_action($definition['hook'], $definition['callback'], $priority, $accepted_args);
		}
	}

	/**
	 * Handle builder errors gracefully - log and display admin notice in dev mode.
	 *
	 * @param \Throwable $e The caught exception or error.
	 * @param string $hook The WordPress hook or context where the error occurred.
	 * @return void
	 */
	public function __handle_builder_error(\Throwable $e, string $hook): void {
		$is_dev_environment = function (): bool {
			if ($this->config !== null && method_exists($this->config, 'is_dev_environment')) {
				return $this->config->is_dev_environment();
			}
			return \defined('WP_DEBUG') && \WP_DEBUG;
		};

		$is_admin = function (): bool {
			return \is_admin();
		};

		$current_user_can = function (string $capability): bool {
			return \current_user_can($capability);
		};

		$add_action = function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
			\add_action($hook, $callback, $priority, $accepted_args);
		};

		$register_fallback_pages = function (\Throwable $e, string $hook, bool $is_dev): void {
			$this->_register_error_fallback_pages($e, $hook, $is_dev);
		};

		$this->_get_error_handler()->handle_builder_error(
			$e,
			$hook,
			$this->logger,
			static::class,
			$is_dev_environment,
			$is_admin,
			$current_user_can,
			$add_action,
			$register_fallback_pages
		);
	}

	/**
	 * Register fallback admin pages that display the error for pages that failed to build.
	 *
	 * This ensures users see the error on the page they were trying to access,
	 * rather than getting "Sorry, you are not allowed to access this page."
	 *
	 * @param \Throwable $e The caught exception or error.
	 * @param string $hook The WordPress hook or context where the error occurred.
	 * @param bool $is_dev Whether we're in development mode (show full details).
	 * @return void
	 */
	protected function _register_error_fallback_pages(\Throwable $e, string $hook, bool $is_dev): void {
		$page_slugs = $this->_extract_page_slugs_from_session();

		$add_menu_page = function (
			string $page_title,
			string $menu_title,
			string $capability,
			string $menu_slug,
			callable $callback,
			string $icon_url = '',
			?int $position = null
		): void {
			$this->_do_add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position);
		};

		$add_submenu_page = function (
			string $parent_slug,
			string $page_title,
			string $menu_title,
			string $capability,
			string $menu_slug,
			callable $callback
		): void {
			$this->_do_add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback);
		};

		$did_action = function (string $hook): bool {
			return (bool) $this->_do_did_action($hook);
		};

		$add_action = function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
			\add_action($hook, $callback, $priority, $accepted_args);
		};

		$this->_get_error_handler()->register_admin_menu_fallback_pages(
			$page_slugs,
			$is_dev,
			$add_menu_page,
			$add_submenu_page,
			$did_action,
			$add_action
		);
	}

	/**
	 * Extract page slugs from the current builder state.
	 *
	 * This method should be overridden by AdminSettings/UserSettings to access
	 * their specific data structures.
	 *
	 * @return array<string> List of page slugs that were being registered.
	 */
	protected function _extract_page_slugs_from_session(): array {
		return array();
	}

	// -- Builder Update Handlers --

	/**
	 * Create an update function for immediate data flow to parent storage.
	 * This eliminates the need for buffering and end() calls by allowing
	 * builders to update parent data structures immediately.
	 *
	 * @return callable The update function that handles all builder updates
	 */
	protected function _create_update_function(): callable {
		$handlers = array(
			'section' => function (array $data): void {
				$this->_handle_section_update($data);
			},
			'section_metadata' => function (array $data): void {
				$this->_handle_section_metadata_update($data);
			},
			'field' => function (array $data): void {
				$this->_handle_field_update($data);
			},
			'group' => function (array $data): void {
				$this->_handle_group_update($data);
			},
			'group_field' => function (array $data): void {
				$this->_handle_group_field_update($data);
			},
			'group_metadata' => function (array $data): void {
				$this->_handle_group_metadata_update($data);
			},
			'section_cleanup' => function (array $data): void {
				$this->_handle_section_cleanup($data);
			},
			'template_override' => function (array $data): void {
				$this->_handle_template_override($data);
			},
			'form_defaults_override' => function (array $data): void {
				$this->_handle_form_defaults_override($data);
			},
			'submit_controls_zone' => function (array $data): void {
				$this->_handle_submit_controls_zone_update($data);
			},
			'submit_controls_set' => function (array $data): void {
				$this->_handle_submit_controls_set_update($data);
			},
		);

		$fallback = function (string $type, array $data): void {
			$this->_handle_context_update($type, $data);
		};

		return $this->_get_update_router()->create_update_function($handlers, $fallback);
	}

	/**
	 * Handle submit controls zone metadata updates.
	 *
	 * @param array<string,mixed> $data Update payload from builder.
	 * @return void
	 */
	protected function _handle_submit_controls_zone_update(array $data): void {
		$container_id = isset($data['container_id']) ? trim((string) $data['container_id']) : '';
		$zone_id      = isset($data['zone_id']) ? trim((string) $data['zone_id']) : '';

		if ($container_id === '' || $zone_id === '') {
			$this->logger->warning('FormsBaseTrait: Submit controls zone update missing required IDs', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
			));
			return;
		}

		$before = $data['before'] ?? null;
		$after  = $data['after']  ?? null;

		$existing_payload = $this->_get_state_store()->get_submit_controls($container_id);
		$current_controls = $existing_payload['controls'] ?? array();
		$current_controls = is_array($current_controls) ? $current_controls : array();
		$updated_payload  = array(
			'zone_id'  => $zone_id,
			'before'   => is_callable($before) ? $before : null,
			'after'    => is_callable($after) ? $after : null,
			'controls' => $current_controls,
		);
		$this->_get_state_store()->set_submit_controls($container_id, $updated_payload);

		$this->logger->debug('forms.submit_controls.zone.updated', array(
			'container_id' => $container_id,
			'zone_id'      => $zone_id,
			'has_before'   => is_callable($before),
			'has_after'    => is_callable($after),
		));
	}

	/**
	 * Handle submit controls set updates.
	 *
	 * @param array<string,mixed> $data Update payload from builder.
	 * @return void
	 */
	protected function _handle_submit_controls_set_update(array $data): void {
		$container_id = isset($data['container_id']) ? trim((string) $data['container_id']) : '';
		$zone_id      = isset($data['zone_id']) ? trim((string) $data['zone_id']) : '';

		if ($container_id === '' || $zone_id === '') {
			$this->logger->warning('FormsBaseTrait: Submit controls set update missing required IDs', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
			));
			return;
		}

		if (!$this->_get_state_store()->has_submit_controls($container_id)) {
			$this->logger->warning('Submit controls update received without matching zone', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
			));
			return;
		}
		$existing = $this->_get_state_store()->get_submit_controls($container_id);

		if (($existing['zone_id'] ?? '') === '') {
			$existing['zone_id'] = $zone_id;
		}

		$controls = $data['controls'] ?? array();
		if (!is_array($controls)) {
			$this->logger->warning('Submit controls update received with invalid controls payload', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
			));
			return;
		}

		$normalized = array();
		foreach ($controls as $control) {
			if (!is_array($control)) {
				continue;
			}

			$control_id = isset($control['id']) ? trim((string) $control['id']) : '';
			$component  = isset($control['component']) ? trim((string) $control['component']) : '';
			$label      = isset($control['label']) ? (string) $control['label'] : '';
			$context    = $control['component_context'] ?? array();

			if ($control_id === '' || $component === '' || !is_array($context)) {
				$this->logger->warning('Submit control entry missing required metadata', array(
					'container_id' => $container_id,
					'zone_id'      => $zone_id,
					'control'      => $control,
				));
				continue;
			}

			$context['field_id'] = $control_id;
			$context['label']    = $label;

			$normalized[] = array(
				'id'                => $control_id,
				'label'             => $label,
				'component'         => $component,
				'component_context' => $context,
				'order'             => isset($control['order']) ? (int) $control['order'] : 0,
			);
		}

		usort(
			$normalized,
			static function(array $a, array $b): int {
				return $a['order'] <=> $b['order'];
			}
		);
		if ($normalized !== array()) {
			$normalized = array_values($normalized);
		}

		$existing['controls'] = $normalized;
		$this->_get_state_store()->set_submit_controls($container_id, $existing);
		if (!empty($normalized)) {
			$this->logger->debug('forms.submit_controls.controls.updated', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
				'count'        => count($normalized),
				'order_map'    => array_map(static function (array $control): array {
					return array(
						'id'    => $control['id']    ?? null,
						'order' => $control['order'] ?? null,
					);
				}, $normalized),
			));
		}
	}

	/**
	 * Handle section update from builders.
	 *
	 * @param array $data Section update data
	 * @return void
	 */
	protected function _handle_section_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$section_data = $data['section_data'] ?? array();

		if ($container_id === '' || $section_id === '') {
			$this->logger->warning('FormsBaseTrait: Section update missing required IDs', $data);
			return;
		}

		$section_entry = array(
			'title'          => (string) ($section_data['title'] ?? ''),
			'description_cb' => $section_data['description_cb'] ?? null,
			'before'         => $section_data['before']         ?? null,
			'after'          => $section_data['after']          ?? null,
			'order'          => (int) ($section_data['order'] ?? 0),
			'index'          => $this->__section_index++,
			'style'          => trim((string) ($section_data['style'] ?? '')),
		);

		$this->_get_state_store()->set_section($container_id, $section_id, $section_entry);
	}

	/**
	 * Handle section metadata update from builders.
	 *
	 * @param array $data Section metadata update payload
	 * @return void
	 */
	protected function _handle_section_metadata_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$group_data   = $data['group_data']   ?? array();

		if ($container_id === '' || $section_id === '') {
			$this->logger->warning('FormsBaseTrait: Section metadata update missing required IDs', $data);
			return;
		}

		if (!$this->_get_state_store()->has_section($container_id, $section_id)) {
			$this->logger->warning('FormsBaseTrait: Section metadata update received before section registration', $data);
			return;
		}

		$section                   = $this->_get_state_store()->get_section($container_id, $section_id);
		$section['title']          = (string) ($group_data['heading'] ?? ($section['title'] ?? ''));
		$section['description_cb'] = $group_data['description'] ?? ($section['description_cb'] ?? null);
		$section['before']         = $group_data['before']      ?? ($section['before'] ?? null);
		$section['after']          = $group_data['after']       ?? ($section['after'] ?? null);
		if (array_key_exists('order', $group_data) && $group_data['order'] !== null) {
			$section['order'] = (int) $group_data['order'];
		}
		if (array_key_exists('style', $group_data)) {
			$section['style'] = trim((string) $group_data['style']);
		}

		$this->_get_state_store()->set_section($container_id, $section_id, $section);
	}

	/**
	 * Handle field update from builders.
	 *
	 * @param array $data Field update data
	 * @return void
	 */
	protected function _handle_field_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$field_data   = $data['field_data']   ?? array();

		if ($container_id === '' || $section_id === '') {
			$this->logger->warning('FormsBaseTrait: Field update missing required IDs', $data);
			return;
		}

		// Validate field data
		$field_id  = $field_data['id'] ?? '';
		$component = isset($field_data['component']) && is_string($field_data['component']) ? trim($field_data['component']) : '';

		if ($field_id === '' || $component === '') {
			throw new \InvalidArgumentException(sprintf('Field "%s" in container "%s" requires id and component metadata.', $field_id !== '' ? $field_id : 'unknown', $container_id));
		}

		$context = $field_data['component_context'] ?? array();
		if (!is_array($context)) {
			throw new \InvalidArgumentException(sprintf('Field "%s" in container "%s" must provide array component_context.', $field_id, $container_id));
		}

		$orderProvided = array_key_exists('order', $field_data ?? array()) && $field_data['order'] !== null;
		$orderValue    = $orderProvided ? (int) $field_data['order'] : 0;

		$field_entry = array(
			'id'                => $field_id,
			'label'             => (string) ($field_data['label'] ?? ''),
			'component'         => $component,
			'component_context' => $context,
			'order'             => $orderValue,
			'index'             => null,
			'before'            => $field_data['before'] ?? null,
			'after'             => $field_data['after']  ?? null,
		);

		$fields  = $this->_get_state_store()->get_fields($container_id, $section_id);
		$updated = false;

		foreach ($fields as $idx => $existing_field) {
			if (($existing_field['id'] ?? '') !== $field_id) {
				continue;
			}

			$field_entry['index'] = $existing_field['index'] ?? $existing_field['order'] ?? $idx;
			if (!$orderProvided) {
				$field_entry['order'] = (int) ($existing_field['order'] ?? 0);
			}
			$fields[$idx] = $field_entry;
			$updated      = true;
			break;
		}

		if (!$updated) {
			// Inject component validators and sanitizers automatically for new fields only
			// Skip for _raw_html and _hr which are just escape hatches for arbitrary markup
			if ($component !== '_raw_html' && $component !== '_hr') {
				$this->_inject_component_validators($field_id, $component, $context);
				$this->_inject_component_sanitizers($field_id, $component, $context);
			}

			$field_entry['index'] = $this->__field_index++;
			$fields[]             = $field_entry;
		}

		usort($fields, function(array $a, array $b): int {
			return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
		});
		$this->_get_state_store()->set_fields($container_id, $section_id, array_values($fields));
	}

	/**
	 * Handle group update from builders.
	 *
	 * @param array $data Group update data
	 * @return void
	 */
	protected function _handle_group_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$group_id     = $data['group_id']     ?? '';
		$group_data   = $data['group_data']   ?? array();

		if ($container_id === '' || $section_id === '' || $group_id === '') {
			$this->logger->warning('FormsBaseTrait: Group update missing required IDs', $data);
			return;
		}

		// Normalize group fields with proper indexing
		$normalized_fields = array();
		$fields            = $group_data['fields'] ?? array();
		foreach ($fields as $field) {
			$field_id  = $field['id'] ?? '';
			$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';

			if ($field_id === '' || $component === '') {
				throw new \InvalidArgumentException(sprintf('Group field "%s" in group "%s" requires id and component metadata.', $field_id !== '' ? $field_id : 'unknown', $group_id));
			}

			$context = $field['component_context'] ?? array();
			if (!is_array($context)) {
				throw new \InvalidArgumentException(sprintf('Group field "%s" in group "%s" must provide array component_context.', $field_id, $group_id));
			}

			$field_entry = array(
				'id'                => $field_id,
				'label'             => (string) ($field['label'] ?? ''),
				'component'         => $component,
				'component_context' => $context,
				'order'             => (int) ($field['order'] ?? 0),
				'index'             => null,
				'before'            => $field['before'] ?? null,
				'after'             => $field['after']  ?? null,
			);

			foreach ($normalized_fields as $idx => $existing_field) {
				if (($existing_field['id'] ?? '') === $field_id) {
					$field_entry['index']    = $existing_field['index'] ?? $existing_field['order'] ?? $idx;
					$normalized_fields[$idx] = $field_entry;
					continue 2;
				}
			}

			// Inject component validators automatically for new group fields only
			$this->_inject_component_validators($field_id, $component);

			$field_entry['index'] = $this->__field_index++;
			$normalized_fields[]  = $field_entry;
		}

		// Store group with proper indexing
		$title = $group_data['heading'] ?? $group_data['title'] ?? '';

		$group_entry = array(
			'group_id' => $group_id,
			'title'    => (string) $title,
			'fields'   => $normalized_fields,
			'before'   => $group_data['before'] ?? null,
			'after'    => $group_data['after']  ?? null,
			'order'    => (int) ($group_data['order'] ?? 0),
			'style'    => trim((string) ($group_data['style'] ?? '')),
			'required' => (bool) ($group_data['required'] ?? false),
			'index'    => $this->__group_index++,
		);

		$this->_get_state_store()->set_group($container_id, $section_id, $group_id, $group_entry);
	}

	/**
	 * Handle group field update from builders.
	 *
	 * @param array $data Group field update data
	 * @return void
	 */
	protected function _handle_group_field_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$group_id     = $data['group_id']     ?? '';
		$field_data   = $data['field_data']   ?? array();

		if ($container_id === '' || $section_id === '' || $group_id === '') {
			$this->logger->warning('FormsBaseTrait: Group field update missing required IDs', $data);
			return;
		}

		// Validate field data
		$field_id  = $field_data['id'] ?? '';
		$component = isset($field_data['component']) && is_string($field_data['component']) ? trim($field_data['component']) : '';

		if ($field_id === '' || $component === '') {
			throw new \InvalidArgumentException(sprintf('Group field "%s" in group "%s" requires id and component metadata.', $field_id !== '' ? $field_id : 'unknown', $group_id));
		}

		$context = $field_data['component_context'] ?? array();
		if (!is_array($context)) {
			throw new \InvalidArgumentException(sprintf('Group field "%s" in group "%s" must provide array component_context.', $field_id, $group_id));
		}

		if (!$this->_get_state_store()->has_group($container_id, $section_id, $group_id)) {
			$this->_get_state_store()->set_group($container_id, $section_id, $group_id, array(
				'group_id' => $group_id,
				'title'    => '',
				'fields'   => array(),
				'before'   => null,
				'after'    => null,
				'order'    => 0,
				'index'    => $this->__group_index++,
			));
		}

		if (!$this->_get_state_store()->has_fields($container_id, $section_id)) {
			$this->_get_state_store()->set_fields($container_id, $section_id, array());
		}

		$group         = $this->_get_state_store()->get_group($container_id, $section_id, $group_id);
		$fields        = $group['fields'] ?? array();
		$fields        = is_array($fields) ? $fields : array();
		$updated       = false;
		$orderProvided = array_key_exists('order', $field_data ?? array()) && $field_data['order'] !== null;
		$orderValue    = $orderProvided ? (int) $field_data['order'] : 0;

		foreach ($fields as $idx => $existing_field) {
			if (($existing_field['id'] ?? '') !== $field_id) {
				continue;
			}

			$fields[$idx] = array(
				'id'                => $field_id,
				'label'             => (string) ($field_data['label'] ?? ''),
				'component'         => $component,
				'component_context' => $context,
				'order'             => $orderProvided ? $orderValue : (int) ($existing_field['order'] ?? 0),
				'index'             => $existing_field['index'] ?? $existing_field['order'] ?? $idx,
				'before'            => $field_data['before']    ?? ($existing_field['before'] ?? null),
				'after'             => $field_data['after']     ?? ($existing_field['after'] ?? null),
			);
			$updated = true;
			break;
		}

		if (!$updated) {
			// Inject component validators automatically for new group fields only
			// Skip for _raw_html and _hr which are just escape hatches for arbitrary markup
			if ($component !== '_raw_html' && $component !== '_hr') {
				$this->_inject_component_validators($field_id, $component);
			}

			$fields[] = array(
				'id'                => $field_id,
				'label'             => (string) ($field_data['label'] ?? ''),
				'component'         => $component,
				'component_context' => $context,
				'order'             => $orderValue,
				'index'             => $this->__field_index++,
				'before'            => $field_data['before'] ?? null,
				'after'             => $field_data['after']  ?? null,
			);
		}

		usort($fields, function(array $a, array $b): int {
			return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
		});
		$group['fields'] = array_values($fields);
		$this->_get_state_store()->set_group($container_id, $section_id, $group_id, $group);
	}

	/**
	 * Handle group metadata update from builders.
	 *
	 * @param array $data Group metadata update data
	 * @return void
	 */
	protected function _handle_group_metadata_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$section_id   = $data['section_id']   ?? '';
		$group_id     = $data['group_id']     ?? '';
		$group_data   = $data['group_data']   ?? array();

		if ($container_id === '' || $section_id === '' || $group_id === '') {
			$this->logger->warning('FormsBaseTrait: Group metadata update missing required IDs', $data);
			return;
		}

		if (!$this->_get_state_store()->has_group($container_id, $section_id, $group_id)) {
			$this->_get_state_store()->set_group($container_id, $section_id, $group_id, array(
				'group_id' => $group_id,
				'fields'   => array(),
				'index'    => $this->__group_index++,
				'before'   => null,
				'after'    => null,
			));
		}

		if (!$this->_get_state_store()->has_fields($container_id, $section_id)) {
			$this->_get_state_store()->set_fields($container_id, $section_id, array());
		}

		// Update group metadata
		$title = $group_data['heading'] ?? $group_data['title'] ?? '';

		$group          = $this->_get_state_store()->get_group($container_id, $section_id, $group_id);
		$group['title'] = (string) $title;
		// Only update before/after if explicitly provided (preserve existing values)
		if (array_key_exists('before', $group_data)) {
			$group['before'] = $group_data['before'];
		}
		if (array_key_exists('after', $group_data)) {
			$group['after'] = $group_data['after'];
		}
		$group['order'] = (int) ($group_data['order'] ?? 0);
		$group['style'] = trim((string) ($group_data['style'] ?? ''));
		$group['type']  = (string) ($group_data['type'] ?? 'group');
		// Fieldset-specific attributes
		$group['form']     = (string) ($group_data['form'] ?? '');
		$group['name']     = (string) ($group_data['name'] ?? '');
		$group['disabled'] = (bool) ($group_data['disabled'] ?? false);

		$this->_get_state_store()->set_group($container_id, $section_id, $group_id, $group);
	}

	/**
	 * Handle section cleanup from builders.
	 *
	 * @param array $data Section cleanup data
	 * @return void
	 */
	protected function _handle_section_cleanup(array $data): void {
		$section_id = $data['section_id'] ?? '';

		if ($section_id === '') {
			$this->logger->warning('FormsBaseTrait: Section cleanup missing section_id', $data);
			return;
		}

		// Remove from active sections tracking (implementation-specific property)
		// This will be overridden by concrete classes if they have active section tracking
		$this->_cleanup_active_section($section_id);
	}

	/**
	 * Handle template override from builders.
	 *
	 * @param array $data Template override data
	 * @return void
	 */
	protected function _handle_template_override(array $data): void {
		$element_type = $data['element_type'] ?? '';
		$element_id   = $data['element_id']   ?? '';
		$overrides    = $data['overrides']    ?? array();

		if ($element_type === '' || $element_id === '' || (empty($overrides) && !isset($data['callback']))) {
			$this->logger->warning('FormsBaseTrait: Template override missing required data', $data);
			return;
		}

		if (!is_array($overrides)) {
			$this->logger->warning('FormsBaseTrait: Template override overrides must be array', $data);
			return;
		}

		// Apply template override via FormsServiceSession
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		if ($element_type === 'root') {
			$should_clear_overrides = empty($overrides);
			if ($should_clear_overrides) {
				$this->form_session->clear_root_template_override($element_id);
			} elseif (!array_key_exists('callback', $data)) {
				// Switching to a string override should remove any previously registered callback.
				$this->form_session->set_root_template_callback($element_id, null);
			}
			if (array_key_exists('callback', $data)) {
				$callback = $data['callback'];
				if (is_callable($callback)) {
					$this->form_session->set_root_template_callback($element_id, $callback);
				} elseif ($callback === null) {
					$this->form_session->set_root_template_callback($element_id, null);
				} else {
					$this->logger->warning('FormsBaseTrait: Template override callback must be callable', array(
						'element_id' => $element_id,
						'callback'   => $callback,
					));
				}
			}
		}

		$zone_id = isset($data['zone_id']) ? trim((string) $data['zone_id']) : '';
		if ($zone_id !== '' && isset($overrides['submit-controls-wrapper'])) {
			$template_key = trim((string) $overrides['submit-controls-wrapper']);
			if ($template_key !== '') {
				$this->form_session->set_submit_controls_override($element_id, $zone_id, $template_key);
			}
			unset($overrides['submit-controls-wrapper']);
		}

		if (!empty($overrides)) {
			$this->form_session->set_individual_element_override($element_type, $element_id, $overrides);
		}
	}

	/**
	 * Handle form defaults override from builders.
	 *
	 * @param array $data Form defaults override data
	 * @return void
	 */
	protected function _handle_form_defaults_override(array $data): void {
		$overrides = $data['overrides'] ?? array();

		if (empty($overrides) || !is_array($overrides)) {
			$this->logger->warning('FormsBaseTrait: Form defaults override missing or invalid overrides', $data);
			return;
		}

		// Apply form defaults override via FormsServiceSession
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$this->form_session->override_form_defaults($overrides);
	}

	/**
	 * Handle custom update types from builders.
	 * Override in concrete classes to handle implementation-specific update types.
	 *
	 * @param string $type The update type
	 * @param array $data Update data
	 * @return void
	 */
	protected function _handle_custom_update(string $type, array $data): void {
		$this->logger->warning('FormsBaseTrait: Unknown update type received', array(
			'type'      => $type,
			'data_keys' => array_keys($data)
		));
	}

	/**
	 * Cleanup active section tracking.
	 * Override in concrete classes that maintain active section arrays.
	 *
	 * @param string $section_id The section ID to cleanup
	 * @return void
	 */
	protected function _cleanup_active_section(string $section_id): void {
		// Default implementation does nothing
		// Override in UserSettingsCollectionBuilder, AdminSettingsPageBuilder, etc.
	}

	/**
	 * Set the default template for this form.
	 *
	 * @param string $template_type The template type.
	 * @param string $template_key The template key.
	 * @return void
	 */
	protected function _set_form_default_template(string $template_type, string $template_key): void {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}
		$this->override_form_defaults(array($template_type => $template_key));
	}

	// Template override methods removed - now handled by FormsTemplateOverrideResolver in FormsServiceSession
	// Use $this->form_session->set_form_defaults(), $this->form_session->set_individual_element_override(), etc.

	// resolve_template() method removed - now handled by FormsServiceSession->resolve_template()



	// Protected

	/**
	 * Start a new form session.
	 */
	protected function _start_form_session(): void {
		if ($this->form_session === null) {
			$this->shared_assets = $this->shared_assets ?? new FormsAssets();
			$pipeline            = null;
			if (isset($this->base_options) && method_exists($this->base_options, 'get_validator_pipeline')) {
				$pipeline = $this->base_options->get_validator_pipeline();
			}
			$this->form_session = $this->form_service->start_session(
				$this->shared_assets,
				array(),
				$pipeline
			);
		}
	}

	/**
	 * Resolve warning messages captured during the most recent sanitize pass for a field ID.
	 *
	 *  @param string $field_id The field ID.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	protected function _get_messages_for_field(string $field_id): array {
		return $this->_get_message_service()->get_messages_for_field($field_id);
	}

	// -- Rendering Helpers --

	/**
	 * Render sections and fields for an admin page.
	 *
	 * @param string $root_id_slug Page identifier.
	 * @param array  $sections  Section metadata map.
	 * @param array  $values    Current option values.
	 *
	 * @return string Rendered HTML markup.
	 */
	protected function _render_default_sections_wrapper(string $id_slug, array $sections, array $values): string {
		return $this->_get_render_service()->render_default_sections_wrapper($id_slug, $sections, $values);
	}

	/**
	 * Get the template alias for rendering sections.
	 *
	 * Override this method in subclasses to use context-specific section templates.
	 * For example, UserSettings overrides this to return 'user.section-wrapper'.
	 *
	 * @return string Template alias for section wrapper
	 */
	protected function _get_section_template(): string {
		return 'layout.zone.section-wrapper';
	}

	/**
	 * Render a group/fieldset using the group-wrapper template.
	 *
	 * @param array<string,mixed> $group Group metadata (group_id, title, style, etc.)
	 * @param string $fields_content Pre-rendered fields HTML
	 * @param string $before_content Pre-rendered before hook HTML
	 * @param string $after_content Pre-rendered after hook HTML
	 * @param array<string,mixed> $values Current field values
	 *
	 * @return string Rendered group HTML
	 */
	protected function _render_group_wrapper(array $group, string $fields_content, string $before_content, string $after_content, array $values): string {
		return $this->_get_render_service()->render_group_wrapper($group, $fields_content, $before_content, $after_content, $values);
	}

	protected function _render_callback_output(?callable $callback, array $context): ?string {
		return $this->_get_render_service()->render_callback_output($callback, $context);
	}

	/**
	 * Render a single field wrappper
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 *
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper(array $field_item, array $values): string {
		return $this->_get_render_service()->render_default_field_wrapper($field_item, $values);
	}

	/**
	 * Render raw HTML content from html() builder method.
	 *
	 * This is an escape hatch for injecting arbitrary markup into forms.
	 * Content is rendered directly without any wrapper.
	 *
	 * @param array<string,mixed> $field The field data with _raw_html component.
	 * @param array<string,mixed> $context Context for callable content (container_id, section_id, values, etc.).
	 * @return string The raw HTML content.
	 */
	protected function _render_raw_html_content(array $field, array $context): string {
		return $this->_get_render_service()->render_raw_html_content($field, $context);
	}

	/**
	 * Render horizontal rule from hr() builder method.
	 *
	 * @param array<string,mixed> $field The field data with _hr component.
	 * @param array<string,mixed> $context Context for before/after callbacks.
	 * @return string The rendered hr HTML.
	 */
	protected function _render_hr_content(array $field, array $context): string {
		return $this->_get_render_service()->render_hr_content($field, $context);
	}

	/**
	 * Context specific render a field wrapper warning.
	 * Uses FormsServiceSession to render error messages with proper template resolution.
	 *
	 * @param string $message The error message
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper_warning(string $message): string {
		return $this->_get_render_service()->render_default_field_wrapper_warning($message);
	}

	// -- Validator/Sanitizer Injection --

	/**
	 * Discover and inject component validators for a field.
	 *
	 * @todo Does ComponetManifest provide a per Component registery of associated validators?
	 *
	 * @param string $field_id Field identifier
	 * @param string $component Component name
	 * @param array  $field_context Field-specific context (options, required, etc.) merged with manifest defaults
	 * @return void
	 */
	protected function _inject_component_validators(string $field_id, string $component, array $field_context = array()): void {
		$this->_get_validator_service()->inject_component_validators($field_id, $component, $field_context);
	}

	/**
	 * Retrieve and clear queued validators awaiting schema registration.
	 *
	 * @internal Used by settings flows to register bucketed schema fragments.
	 *
	 * @return array<string, array<int, callable>>
	 */
	protected function _drain_queued_component_validators(): array {
		return $this->_get_validator_service()->drain_queued_component_validators();
	}

	/**
	 * Discover and inject component sanitizers for a field.
	 *
	 * Mirrors _inject_component_validators() but for sanitizers.
	 * Sanitizers are optional – components without a Sanitizer.php are silently skipped.
	 *
	 * @param string $field_id Field identifier
	 * @param string $component Component name
	 * @param array  $field_context Field-specific context (options, required, etc.) merged with manifest defaults
	 * @return void
	 */
	protected function _inject_component_sanitizers(string $field_id, string $component, array $field_context = array()): void {
		$this->_get_validator_service()->inject_component_sanitizers($field_id, $component, $field_context);
	}

	/**
	 * Retrieve and clear queued sanitizers awaiting schema registration.
	 *
	 * @internal Used by settings flows to register bucketed schema fragments.
	 *
	 * @return array<string, array<int, callable>>
	 */
	protected function _drain_queued_component_sanitizers(): array {
		return $this->_get_validator_service()->drain_queued_component_sanitizers();
	}

	// -- Schema Bundle Resolution --

	/**
	 * Resolve and memoize schema bundle for current request/context.
	 *
	 * @param RegisterOptions $options
	 * @param array<string,mixed> $context
	 * @return array{
	 *     schema: array<string,array>,
	 *     defaults: array<string,array{default:mixed}>,
	 *     bucketed_schema: array<string,array>,
	 *     metadata: array<string,array<string,mixed>>,
	 *     queued_validators: array<string,array<int,callable>>,
	 *     queued_sanitizers: array<string,array<int,callable>>
	 * }
	 */
	protected function _resolve_schema_bundle(RegisterOptions $options, array $context = array()): array {
		return $this->_get_schema_service()->resolve_schema_bundle($options, $context);
	}

	/**
	 * Merge schema bundle sources into a single registration-ready array.
	 *
	 * Consolidates bucketed_schema, schema, and defaults into one merged schema
	 * that can be registered with a single `_register_internal_schema()` call.
	 *
	 * Merge precedence:
	 * 1. bucketed_schema (component validators) - base layer
	 * 2. schema (developer schema validators) - merged on top
	 * 3. defaults - only fills missing keys (does not override validators)
	 *
	 * @param array $bundle Schema bundle from _resolve_schema_bundle().
	 * @return array{
	 *     merged_schema: array<string, array>,
	 *     metadata: array<string, array<string, mixed>>,
	 *     queued_validators: array<string, array<int, callable>>,
	 *     queued_sanitizers: array<string, array<int, callable>>,
	 *     defaults_for_seeding: array<string, array>
	 * }
	 */
	protected function _merge_schema_bundle_sources(array $bundle): array {
		return $this->_get_schema_service()->merge_schema_bundle_sources($bundle);
	}

	/**
	 * Merge two schema entry bucket structures.
	 *
	 * Combines sanitize and validate buckets from two schema entries,
	 * preserving both component and schema-level callables.
	 *
	 * @param array $existing The existing schema entry.
	 * @param array $incoming The incoming schema entry to merge.
	 * @return array The merged schema entry.
	 */
	protected function _merge_schema_entry_buckets(array $existing, array $incoming): array {
		return $this->_get_schema_service()->merge_schema_entry_buckets($existing, $incoming);
	}

	/**
	 * Builds the initial bucketed schema fragments and metadata for registered fields.
	 *
	 * This method:
	 * 1. Iterates registered fields and builds bucketed schema entries
	 * 2. Consumes queued component validators and sanitizers
	 * 3. Returns a complete schema bundle ready for merging
	 *
	 * This collects component-provided defaults plus any per-field schema declared on the
	 * builders, but it does not reflect the full developer-supplied schema map. Callers are
	 * expected to merge the returned fragment first, then layer the main schema via
	 * RegisterOptions::register_schema()/_register_internal_schema().
	 *
	 * @internal Consumed by settings facades during auto-schema backfill.
	 *
	 * @param FormsServiceSession $session
	 * @return array{
	 *     schema: array<string, array{
	 *         sanitize: array{component: array<int, callable>, schema: array<int, callable>},
	 *         validate: array{component: array<int, callable>, schema: array<int, callable>},
	 *         default?: mixed
	 *     }>,
	 *     metadata: array<string, array<string, mixed>>,
	 *     queued_validators: array<string, array<int, callable>>,
	 *     queued_sanitizers: array<string, array<int, callable>>
	 * }
	 */
	protected function _assemble_initial_bucketed_schema(FormsServiceSession $session): array {
		return $this->_get_schema_service()->assemble_initial_bucketed_schema($session);
	}

	/**
	 * Consume queued validators for the supplied schema fragment while preserving order.
	 *
	 * Any validators that do not correspond to the provided schema keys are re-queued so a
	 * later drain can merge them once their schema entries materialize.
	 *
	 * @param array<string, array<string, mixed>> $bucketedSchema
	 * @return array{
	 *     0: array<string, array<string, mixed>>,
	 *     1: array<string, array<int, callable>>
	 * }
	 */
	protected function _consume_component_validator_queue(array $bucketedSchema): array {
		return $this->_get_validator_service()->consume_component_validator_queue($bucketedSchema);
	}

	/**
	 * Consume queued sanitizers for the supplied schema fragment while preserving order.
	 *
	 * Mirrors _consume_component_validator_queue() but for sanitizers.
	 * Any sanitizers that do not correspond to the provided schema keys are re-queued so a
	 * later drain can merge them once their schema entries materialize.
	 *
	 * @param array<string, array<string, mixed>> $bucketedSchema
	 * @return array{
	 *     0: array<string, array<string, mixed>>,
	 *     1: array<string, array<int, callable>>
	 * }
	 */
	protected function _consume_component_sanitizer_queue(array $bucketedSchema): array {
		return $this->_get_validator_service()->consume_component_sanitizer_queue($bucketedSchema);
	}

	// -- File Upload Utilities --

	/**
	 * Check if a container (page/collection) contains file upload fields.
	 *
	 * @param string $container_id The container ID to check.
	 * @return bool True if the container has file upload fields.
	 */
	protected function _container_has_file_uploads(string $container_id): bool {
		return $this->_get_render_service()->container_has_file_uploads($container_id);
	}

	/**
	 * Process uploaded files from $_FILES for the main option.
	 *
	 * Iterates through uploaded files, validates them, and processes each one.
	 * Returns an array of field_key => processed_file_data pairs.
	 *
	 * @return array<string, array<string,mixed>> Processed file data keyed by field.
	 */
	protected function _process_uploaded_files(): array {
		return $this->_get_file_upload_service()->process_uploaded_files($_FILES);
	}

	/**
	 * Process a single file upload using WordPress functions.
	 *
	 * @param array<string,mixed> $file The file data from $_FILES.
	 * @return array<string,mixed>|null The processed file data or null on failure.
	 */
	protected function _process_single_file_upload(array $file): ?array {
		return $this->_get_file_upload_service()->process_single_file_upload($file);
	}

	/**
	 * Create a media library attachment for an uploaded file.
	 *
	 * @param array<string,mixed> $uploadResult The result from wp_handle_upload.
	 * @return int|null The attachment ID or null on failure.
	 */
	protected function _create_media_attachment(array $uploadResult): ?int {
		return $this->_get_file_upload_service()->create_media_attachment($uploadResult);
	}
}
