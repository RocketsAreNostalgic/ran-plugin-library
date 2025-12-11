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
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\Component\ComponentType;
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

	// Template override system removed - now handled by FormsTemplateOverrideResolver in FormsServiceSession

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;

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
		$messages = $this->message_handler->get_all_messages();
		$this->message_handler->clear();
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
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
		$callback = $this->form_session->get_root_template_callback($container_id);
		if ($callback !== null) {
			ob_start();
			$callback($payload);
			echo (string) ob_get_clean();
		} else {
			echo $this->form_session->render_element('root-wrapper', $payload, array(
				'root_id' => $container_id,
				...$element_context,
			));
		}
		$this->form_session->enqueue_assets();
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
		$entries = array();

		foreach ($this->fields as $container_id => $sections) {
			foreach ($sections as $section_id => $fields) {
				foreach ($fields as $field) {
					$field_entry = is_array($field) ? $field : array();
					$entries[]   = array(
						'container_id' => (string) $container_id,
						'section_id'   => (string) $section_id,
						'group_id'     => null,
						'field'        => $field_entry,
					);
				}
			}
		}

		foreach ($this->groups as $container_id => $sections) {
			foreach ($sections as $section_id => $groups) {
				foreach ($groups as $group_id => $group) {
					$group_fields = isset($group['fields']) && is_array($group['fields']) ? $group['fields'] : array();
					foreach ($group_fields as $field) {
						$field_entry = is_array($field) ? $field : array();
						$group_entry = is_array($group) ? $group : array();
						$entries[]   = array(
							'container_id' => (string) $container_id,
							'section_id'   => (string) $section_id,
							'group_id'     => (string) $group_id,
							'field'        => $field_entry,
							'group'        => $group_entry,
						);
					}
				}
			}
		}

		return $entries;
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
		if ($field_id === '') {
			return null;
		}

		foreach ($this->fields as $container) {
			foreach ($container as $section) {
				foreach ($section as $field) {
					if (($field['id'] ?? '') === $field_id) {
						$component = $field['component'] ?? null;
						return is_string($component) && $component !== '' ? $component : null;
					}
				}
			}
		}

		foreach ($this->groups as $container) {
			foreach ($container as $section) {
				foreach ($section as $group) {
					foreach ($group['fields'] ?? array() as $field) {
						if (($field['id'] ?? '') === $field_id) {
							$component = $field['component'] ?? null;
							return is_string($component) && $component !== '' ? $component : null;
						}
					}
				}
			}
		}

		foreach ($this->submit_controls as $container) {
			foreach ($container['controls'] ?? array() as $control) {
				if (($control['id'] ?? '') === $field_id) {
					$component = $control['component'] ?? null;
					return is_string($component) && $component !== '' ? $component : null;
				}
			}
		}

		return null;
	}

	// -- Validation Message Helpers --

	/**
	 * Prepare the message handler for a new validation run.
	 *
	 * @param array<string,mixed> $payload
	 * @return void
	 */
	protected function _prepare_validation_messages(array $payload): void {
		$this->message_handler->clear();
		$this->message_handler->set_pending_values($payload);
		$this->pending_values = $payload;
	}

	/**
	 * Capture validation messages emitted by the provided RegisterOptions instance.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	protected function _process_validation_messages(RegisterOptions $options): array {
		$messages = $options->take_messages();
		$this->message_handler->set_messages($messages);
		return $messages;
	}

	/**
	 * Determine whether validation failures were recorded during the current operation.
	 */
	protected function _has_validation_failures(): bool {
		return $this->message_handler->has_validation_failures();
	}

	/**
	 * Clear pending validation state after a successful operation.
	 */
	protected function _clear_pending_validation(): void {
		$this->message_handler->set_pending_values(null);
		$this->pending_values = null;
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
		if (!array_key_exists('warning_count', $context)) {
			$context['warning_count'] = $this->message_handler->get_warning_count();
		}
		switch ($level) {
			case 'warning':
				$this->logger->warning($message, $context);
				break;
			case 'error':
				$this->logger->error($message, $context);
				break;
			case 'debug':
				$this->logger->debug($message, $context);
				break;
			default:
				$this->logger->info($message, $context);
		}
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
		switch ($level) {
			case 'info':
				$this->logger->info($message, $context);
				break;
			case 'warning':
				$this->logger->warning($message, $context);
				break;
			case 'error':
				$this->logger->error($message, $context);
				break;
			default:
				$this->logger->debug($message, $context);
		}
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
		if ($user_id === null) {
			$user_id = $this->_do_get_current_user_id();
		}
		// Include form type to prevent different form classes from consuming each other's messages
		$form_type = $this->_get_form_type_suffix();
		return 'ran_form_messages_' . $form_type . '_' . $this->main_option . '_' . $user_id;
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
		if (empty($messages)) {
			return;
		}

		$key = $this->_get_form_messages_transient_key($user_id);
		$this->_do_set_transient($key, $messages, 30); // 30 second TTL

		$this->logger->debug('forms.messages_persisted', array(
			'transient_key' => $key,
			'field_count'   => count($messages),
		));
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
		$key      = $this->_get_form_messages_transient_key($user_id);
		$messages = $this->_do_get_transient($key);

		if (empty($messages) || !is_array($messages)) {
			return false;
		}

		// Delete transient after reading (one-time display)
		$this->_do_delete_transient($key);

		// Feed messages into our message handler
		$this->message_handler->set_messages($messages);

		$this->logger->debug('forms.messages_restored', array(
			'transient_key' => $key,
			'field_count'   => count($messages),
		));

		return true;
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
		$context = array(
			'hook'  => $hook,
			'class' => static::class,
			'file'  => $e->getFile(),
			'line'  => $e->getLine(),
			'trace' => $e->getTraceAsString(),
		);

		// Always log the error
		$this->logger->error(
			sprintf('Settings builder error on %s hook: %s', $hook, $e->getMessage()),
			$context
		);

		// Only proceed if in admin and user can manage options
		// Note: Using raw WP functions here since this trait is used by classes
		// that may not have WPWrappersTrait (e.g., AdminSettings)
		if (!\is_admin() || !\current_user_can('manage_options')) {
			return;
		}

		$is_dev = $this->_is_dev_environment();

		// Show admin notice in dev mode
		if ($is_dev) {
			\add_action('admin_notices', function () use ($e, $hook) {
				$this->_render_builder_error_notice($e, $hook);
			});
		}

		// Always register fallback error pages so routes are valid
		$this->_register_error_fallback_pages($e, $hook, $is_dev);
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
		// Extract page slugs from the session data that was being built
		$page_slugs = $this->_extract_page_slugs_from_session();

		if (empty($page_slugs)) {
			return;
		}

		// Get the first slug as the main menu, rest as subpages
		$main_slug = array_shift($page_slugs);

		$register_pages = function () use ($main_slug, $page_slugs, $is_dev) {
			// Brief page content - full error details shown in admin_notices
			$render_error = function () use ($is_dev) {
				echo '<div class="wrap">';
				if ($is_dev) {
					echo '<h1>Settings Builder Errors</h1>';
				} else {
					echo '<h1>Settings Unavailable</h1>';
					echo '<p>This settings page is temporarily unavailable. ';
					echo 'Please contact the site administrator if this problem persists.</p>';
				}
				echo '</div>';
			};

			// Register main menu page
			$this->_do_add_menu_page(
				$is_dev ? 'Settings Error' : 'Settings',
				$is_dev ? 'Settings Error' : 'Settings',
				'manage_options',
				$main_slug,
				$render_error,
				$is_dev ? 'dashicons-warning' : 'dashicons-admin-generic',
				999
			);

			// Register subpages under the main menu
			foreach ($page_slugs as $slug) {
				$this->_do_add_submenu_page(
					$main_slug,
					$is_dev ? 'Settings Error' : 'Settings',
					$is_dev ? 'Settings Error' : 'Settings',
					'manage_options',
					$slug,
					$render_error
				);
			}
		};

		// Check if admin_menu has already fired
		if ($this->_do_did_action('admin_menu')) {
			$register_pages();
		} else {
			\add_action('admin_menu', $register_pages, 999);
		}
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

	/**
	 * Render an admin notice for builder errors (dev mode only).
	 *
	 * @param \Throwable $e The caught exception or error.
	 * @param string $hook The WordPress hook where the error occurred.
	 * @return void
	 */
	protected function _render_builder_error_notice(\Throwable $e, string $hook): void {
		ErrorNoticeRenderer::renderWithContext($e, 'Settings Builder Error', 'hook', $hook);
	}

	/**
	 * Check if we're in a development environment.
	 *
	 * Uses Config if available, falls back to WP_DEBUG.
	 *
	 * @return bool
	 */
	protected function _is_dev_environment(): bool {
		if ($this->config !== null && method_exists($this->config, 'is_dev_environment')) {
			return $this->config->is_dev_environment();
		}
		return \defined('WP_DEBUG') && \WP_DEBUG;
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
		return function(string $type, array $data): void {
			switch ($type) {
				case 'section':
					$this->_handle_section_update($data);
					break;
				case 'section_metadata':
					$this->_handle_section_metadata_update($data);
					break;
				case 'field':
					$this->_handle_field_update($data);
					break;
				case 'group':
					$this->_handle_group_update($data);
					break;
				case 'group_field':
					$this->_handle_group_field_update($data);
					break;
				case 'group_metadata':
					$this->_handle_group_metadata_update($data);
					break;
				case 'section_cleanup':
					$this->_handle_section_cleanup($data);
					break;
				case 'template_override':
					$this->_handle_template_override($data);
					break;
				case 'form_defaults_override':
					$this->_handle_form_defaults_override($data);
					break;
				case 'submit_controls_zone':
					$this->_handle_submit_controls_zone_update($data);
					break;
				case 'submit_controls_set':
					$this->_handle_submit_controls_set_update($data);
					break;
				default:
					// Allow concrete classes to handle implementation-specific update types
					$this->_handle_context_update($type, $data);
					break;
			}
		};
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

		$current_controls = $this->submit_controls[$container_id]['controls'] ?? array();

		$this->submit_controls[$container_id] = array(
			'zone_id'  => $zone_id,
			'before'   => is_callable($before) ? $before : null,
			'after'    => is_callable($after) ? $after : null,
			'controls' => $current_controls,
		);

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

		$existing = $this->submit_controls[$container_id] ?? null;
		if ($existing === null) {
			$this->logger->warning('Submit controls update received without matching zone', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
			));
			return;
		}

		if (($existing['zone_id'] ?? '') === '') {
			$this->submit_controls[$container_id]['zone_id'] = $zone_id;
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

		$this->submit_controls[$container_id]['controls'] = $normalized;
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

		// Initialize arrays if needed
		if (!isset($this->sections[$container_id])) {
			$this->sections[$container_id] = array();
		}

		// Store section with proper indexing
		$this->sections[$container_id][$section_id] = array(
			'title'          => (string) ($section_data['title'] ?? ''),
			'description_cb' => $section_data['description_cb'] ?? null,
			'before'         => $section_data['before']         ?? null,
			'after'          => $section_data['after']          ?? null,
			'order'          => (int) ($section_data['order'] ?? 0),
			'index'          => $this->__section_index++,
			'style'          => trim((string) ($section_data['style'] ?? '')),
		);
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

		if (!isset($this->sections[$container_id][$section_id])) {
			$this->logger->warning('FormsBaseTrait: Section metadata update received before section registration', $data);
			return;
		}

		$section                   = &$this->sections[$container_id][$section_id];
		$section['title']          = (string) ($group_data['heading'] ?? $section['title']);
		$section['description_cb'] = $group_data['description'] ?? $section['description_cb'];
		$section['before']         = $group_data['before']      ?? $section['before'];
		$section['after']          = $group_data['after']       ?? $section['after'];
		if (array_key_exists('order', $group_data) && $group_data['order'] !== null) {
			$section['order'] = (int) $group_data['order'];
		}
		if (array_key_exists('style', $group_data)) {
			$section['style'] = trim((string) $group_data['style']);
		}
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

		// Initialize arrays if needed
		if (!isset($this->fields[$container_id])) {
			$this->fields[$container_id] = array();
		}
		if (!isset($this->fields[$container_id][$section_id])) {
			$this->fields[$container_id][$section_id] = array();
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

		$fields  = & $this->fields[$container_id][$section_id];
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
		$this->fields[$container_id][$section_id] = array_values($fields);
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

		// Initialize arrays if needed
		if (!isset($this->groups[$container_id])) {
			$this->groups[$container_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id])) {
			$this->groups[$container_id][$section_id] = array();
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

		$this->groups[$container_id][$section_id][$group_id] = array(
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

		// Initialize arrays if needed
		if (!isset($this->groups[$container_id])) {
			$this->groups[$container_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id])) {
			$this->groups[$container_id][$section_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id][$group_id])) {
			$this->groups[$container_id][$section_id][$group_id] = array(
				'group_id' => $group_id,
				'title'    => '', // Will be set when group metadata is sent
				'fields'   => array(),
				'before'   => null,
				'after'    => null,
				'order'    => 0,
				'index'    => $this->__group_index++,
			);
		}

		// Ensure section-level field container exists (for sections with only groups)
		if (!isset($this->fields[$container_id])) {
			$this->fields[$container_id] = array();
		}
		if (!isset($this->fields[$container_id][$section_id])) {
			$this->fields[$container_id][$section_id] = array();
		}

		$fields        = & $this->groups[$container_id][$section_id][$group_id]['fields'];
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
		$this->groups[$container_id][$section_id][$group_id]['fields'] = array_values($fields);
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

		// Initialize arrays if needed
		if (!isset($this->groups[$container_id])) {
			$this->groups[$container_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id])) {
			$this->groups[$container_id][$section_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id][$group_id])) {
			$this->groups[$container_id][$section_id][$group_id] = array(
				'group_id' => $group_id,
				'fields'   => array(),
				'index'    => $this->__group_index++,
				'before'   => null,
				'after'    => null,
			);
		}

		// Ensure the corresponding section field container exists even if no standalone fields were added
		if (!isset($this->fields[$container_id])) {
			$this->fields[$container_id] = array();
		}
		if (!isset($this->fields[$container_id][$section_id])) {
			$this->fields[$container_id][$section_id] = array();
		}

		// Update group metadata
		$title = $group_data['heading'] ?? $group_data['title'] ?? '';

		$this->groups[$container_id][$section_id][$group_id]['title'] = (string) $title;
		// Only update before/after if explicitly provided (preserve existing values)
		if (array_key_exists('before', $group_data)) {
			$this->groups[$container_id][$section_id][$group_id]['before'] = $group_data['before'];
		}
		if (array_key_exists('after', $group_data)) {
			$this->groups[$container_id][$section_id][$group_id]['after'] = $group_data['after'];
		}
		$this->groups[$container_id][$section_id][$group_id]['order'] = (int) ($group_data['order'] ?? 0);
		$this->groups[$container_id][$section_id][$group_id]['style'] = trim((string) ($group_data['style'] ?? ''));
		$this->groups[$container_id][$section_id][$group_id]['type']  = (string) ($group_data['type'] ?? 'group');
		// Fieldset-specific attributes
		$this->groups[$container_id][$section_id][$group_id]['form']     = (string) ($group_data['form'] ?? '');
		$this->groups[$container_id][$section_id][$group_id]['name']     = (string) ($group_data['name'] ?? '');
		$this->groups[$container_id][$section_id][$group_id]['disabled'] = (bool) ($group_data['disabled'] ?? false);
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
		$key      = $this->_do_sanitize_key($field_id);
		$messages = $this->message_handler->get_messages_for_field($key);
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
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
		$groups_map = $this->groups[$id_slug] ?? array();
		$fields_map = $this->fields[$id_slug] ?? array();

		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$all_sections_markup = '';

		foreach ($sections as $section_id => $meta) {
			$groups = $groups_map[$section_id] ?? array();
			$fields = $fields_map[$section_id] ?? array();

			// Sort groups and fields by order
			uasort($groups, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});
			usort($fields, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});

			// Pre-render all content for this section
			$section_content = '';

			// Render groups first
			foreach ($groups as $group) {
				$group_fields = $group['fields'];
				usort($group_fields, function ($a, $b) {
					return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
				});

				// Render group fields content
				$group_fields_content = '';
				foreach ($group_fields as $group_field) {
					// Handle raw HTML injection (escape hatch)
					if (($group_field['component'] ?? '') === '_raw_html') {
						$group_fields_content .= $this->_render_raw_html_content($group_field, array(
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						));
						continue;
					}

					// Handle horizontal rule
					if (($group_field['component'] ?? '') === '_hr') {
						$group_fields_content .= $this->_render_hr_content($group_field, array(
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						));
						continue;
					}

					$field_item = array(
						'field'  => $group_field,
						'before' => $this->_render_callback_output($group_field['before'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						)),
						'after' => $this->_render_callback_output($group_field['after'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						)),
						'group_type' => $group['type'] ?? 'group',
					);
					$group_fields_content .= $this->_render_default_field_wrapper($field_item, $values);
				}

				// Render group before/after callbacks
				$group_before = $this->_render_callback_output($group['before'] ?? null, array(
					'group_id'     => $group['group_id'] ?? '',
					'section_id'   => $section_id,
					'container_id' => $id_slug,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';
				$group_after = $this->_render_callback_output($group['after'] ?? null, array(
					'group_id'     => $group['group_id'] ?? '',
					'section_id'   => $section_id,
					'container_id' => $id_slug,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';

				// Render group using wrapper template
				$section_content .= $this->_render_group_wrapper($group, $group_fields_content, $group_before, $group_after, $values);
			}

			// Render standalone fields
			foreach ($fields as $field) {
				// Handle raw HTML injection (escape hatch)
				if (($field['component'] ?? '') === '_raw_html') {
					$section_content .= $this->_render_raw_html_content($field, array(
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					));
					continue;
				}

				// Handle horizontal rule
				if (($field['component'] ?? '') === '_hr') {
					$section_content .= $this->_render_hr_content($field, array(
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					));
					continue;
				}

				$field_item = array(
					'field'  => $field,
					'before' => $this->_render_callback_output($field['before'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					)),
					'after' => $this->_render_callback_output($field['after'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					)),
				);
				$section_content .= $this->_render_default_field_wrapper($field_item, $values);
			}

			// Render section template with pre-rendered content
			// Use form_session to respect context-specific template overrides (e.g., user.section-wrapper)
			$section_style   = trim((string) ($meta['style'] ?? ''));
			$description_cb  = $meta['description_cb'] ?? null;
			$section_context = array(
				'section_id'  => $section_id,
				'title'       => (string) $meta['title'],
				'description' => is_callable($description_cb) ? (string) ($description_cb)() : (string) ($description_cb ?? ''),
				'inner_html'  => $section_content,
				'before'      => $this->_render_callback_output($meta['before'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) ?? '',
				'after' => $this->_render_callback_output($meta['after'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) ?? '',
				'style' => trim($section_style),
			);

			// Render section using the context-appropriate template
			$section_template = $this->_get_section_template();
			$sectionComponent = $this->views->render($section_template, $section_context);

			if (!$sectionComponent instanceof ComponentRenderResult) {
				throw new UnexpectedValueException('Section template must return a ComponentRenderResult instance.');
			}

			$this->form_session->ingest_component_result(
				$sectionComponent,
				'render_section',
				null
			);

			$all_sections_markup .= $sectionComponent->markup;
		}

		return $all_sections_markup;
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
		$group_id = $group['group_id'] ?? '';
		$title    = $group['title']    ?? '';
		$style    = trim((string) ($group['style'] ?? ''));

		// Build context for the group wrapper template
		$group_context = array(
			'group_id'    => $group_id,
			'title'       => $title,
			'description' => '', // Could be added if groups support description callbacks
			'inner_html'  => $fields_content,
			'before'      => $before_content,
			'after'       => $after_content,
			'layout'      => 'vertical',
			'spacing'     => 'normal',
			'style'       => $style,
			'values'      => $values,
		);

		// Try to render using the group-wrapper template
		try {
			if ($this->form_session === null) {
				$this->_start_form_session();
			}

			$result = $this->form_session->render_component('group-wrapper', $group_context);
			if ($result !== '') {
				return $result;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('FormsBaseTrait: Group wrapper template failed, using fallback', array(
				'group_id'          => $group_id,
				'exception_message' => $e->getMessage(),
			));
		}

		// Fallback: render without template
		$group_classes = array('group-wrapper');
		if ($style !== '') {
			$group_classes[] = $style;
		}
		$output = '';
		if ($title !== '') {
			$output .= '<div class="' . esc_attr(implode(' ', $group_classes)) . '" data-group-id="' . esc_attr($group_id) . '">';
			$output .= '<h4 class="group-wrapper__title">' . esc_html($title) . '</h4>';
			$output .= '<div class="group-wrapper__content">';
		}
		$output .= $before_content;
		$output .= $fields_content;
		$output .= $after_content;
		if ($title !== '') {
			$output .= '</div></div>';
		}

		return $output;
	}

	protected function _render_callback_output(?callable $callback, array $context): ?string {
		if ($callback === null) {
			return null;
		}

		if (!is_callable($callback)) {
			$this->logger->warning('FormsBaseTrait: Callback provided is not callable', array('context_keys' => array_keys($context)));
			return null;
		}

		$context_keys = array_keys($context);

		try {
			$result         = (string) $callback($context);
			$result_length  = strlen($result);
			$preview_length = 120;
			$this->logger->debug('FormsBaseTrait: Callback executed', array(
				'context_keys'     => $context_keys,
				'result_length'    => $result_length,
				'result_preview'   => $preview_length >= $result_length ? $result : substr($result, 0, $preview_length),
				'result_truncated' => $result_length > $preview_length,
			));
			return $result;
		} catch (\Throwable $e) {
			$this->logger->error('FormsBaseTrait: Callback execution failed', array(
				'context_keys'      => $context_keys,
				'exception_class'   => get_class($e),
				'exception_message' => $e->getMessage(),
			));
			return null;
		}
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
		$field = $field_item['field'] ?? $field_item;
		if (empty($field)) {
			return '';
		}

		$field_id  = isset($field['id']) ? (string) $field['id'] : '';
		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';
		$this->logger->debug('forms.default_field.render', array(
			'field_id'  => $field_id,
			'component' => $component,
		));

		if ($component === '') {
			$this->logger->error(static::class . ': field missing component metadata.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(static::class . ': field "%s" requires a component alias.', $field_id ?: 'unknown'));
		}

		$component_context = $field['component_context'] ?? array();
		if (!is_array($component_context)) {
			$this->logger->error( static::class . ': field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(static::class . ': field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}

		// Prepare field configuration
		$field['field_id']          = $field_id;
		$field['component']         = $component;
		$field['label']             = $label;
		$field['component_context'] = $component_context;

		// Set the name attribute with proper prefix for form submission
		if (!isset($field['name']) && $field_id !== '') {
			$field['name'] = $this->main_option . '[' . $field_id . ']';
		}

		// Use FormElementRenderer for complete field processing with wrapper
		try {
			if ($this->form_session === null) {
				$this->_start_form_session();
			}

			$extras = array();
			// before/after are already rendered strings from the caller
			if (array_key_exists('before', $field_item) && $field_item['before'] !== null) {
				$extras['before'] = (string) $field_item['before'];
			}
			if (array_key_exists('after', $field_item) && $field_item['after'] !== null) {
				$extras['after'] = (string) $field_item['after'];
			}

			$field_context = $this->field_renderer->prepare_field_context(
				$field,
				$values,
				$extras
			);

			// Determine wrapper template based on group type
			// Fields inside fieldsets use fieldset-field-wrapper for proper table row rendering
			$group_type  = $field_item['group_type'] ?? 'group';
			$wrapper_key = $group_type === 'fieldset' ? 'fieldset-field-wrapper' : 'field-wrapper';

			// Let FormElementRenderer handle both component rendering and wrapper application
			return $this->field_renderer->render_field_with_wrapper(
				$component,
				$field_id,
				$label,
				$field_context,
				$wrapper_key,
				$wrapper_key,
				$this->form_session
			);
		} catch (\Throwable $e) {
			$this->logger->error(static::class . ': Field rendering failed', array(
				'field_id'          => $field_id,
				'component'         => $component,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));
			// @TODO will this break table based layouts?
			return $this->_render_default_field_wrapper_warning($e->getMessage());
		}
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
		$content = $field['component_context']['content'] ?? '';

		if (is_callable($content)) {
			return (string) $content($context);
		}

		return (string) $content;
	}

	/**
	 * Render horizontal rule from hr() builder method.
	 *
	 * @param array<string,mixed> $field The field data with _hr component.
	 * @param array<string,mixed> $context Context for before/after callbacks.
	 * @return string The rendered hr HTML.
	 */
	protected function _render_hr_content(array $field, array $context): string {
		$component_context = $field['component_context'] ?? array();
		$style_classes     = trim($component_context['style'] ?? '');

		// Render before callback
		$before = '';
		if (isset($field['before']) && is_callable($field['before'])) {
			$before = (string) ($field['before'])($context);
		}

		// Render after callback
		$after = '';
		if (isset($field['after']) && is_callable($field['after'])) {
			$after = (string) ($field['after'])($context);
		}

		// Build hr element - style() provides CSS classes per builder convention
		$class_attr = 'kplr-hr' . ($style_classes !== '' ? ' ' . $style_classes : '');

		$hr = '<hr class="' . esc_attr($class_attr) . '">';

		return $before . $hr . $after;
	}

	/**
	 * Context specific render a field wrapper warning.
	 * Uses FormsServiceSession to render error messages with proper template resolution.
	 *
	 * @param string $message The error message
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper_warning(string $message): string {
		// Use FormsServiceSession to render error with proper template resolution
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		// Render error using the field-wrapper template with error context
		return $this->form_session->render_component('field-wrapper', array(
			'field_id'      => 'error',
			'label'         => 'Error',
			'inner_html'    => esc_html($message),
			'is_error'      => true,
			'error_message' => $message
		));
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
		$field_key       = $this->base_options->normalize_schema_key($field_id);
		$defaults        = $this->components->get_defaults_for($component);
		$manifestContext = is_array($defaults['context'] ?? null) ? $defaults['context'] : array();
		// Merge manifest defaults with field-specific context; field context takes precedence
		$context       = array_merge($manifestContext, $field_context);
		$componentType = isset($context['component_type']) ? (string) $context['component_type'] : '';
		$submits       = $componentType === ComponentType::FormField->value;

		$validator_factories = $this->components->validator_factories();
		$factory             = $validator_factories[$component] ?? null;
		if (!is_callable($factory)) {
			if ($submits) {
				$this->logger->error(static::class . ': Component missing validator for data-submitting field', array(
					'field_id'  => $field_id,
					'component' => $component,
				));
				throw new \UnexpectedValueException(sprintf('Component "%s" must provide a validator for field "%s".', $component, $field_id));
			}
			return;
		}

		$validator_instance = $factory();
		$validator_callable = function($value, callable $emitWarning) use ($validator_instance, $context): bool {
			return $validator_instance->validate($value, $context, $emitWarning);
		};

		$hadSchema                                         = $this->base_options->has_schema_key($field_key);
		$this->__queued_component_validators[$field_key][] = $validator_callable;
		$this->logger->debug(static::class . ': Component validator queued pending schema', array(
			'field_id'          => $field_id,
			'component'         => $component,
			'schema_registered' => $hadSchema,
		));
	}

	/**
	 * Retrieve and clear queued validators awaiting schema registration.
	 *
	 * @internal Used by settings flows to register bucketed schema fragments.
	 *
	 * @return array<string, array<int, callable>>
	 */
	protected function _drain_queued_component_validators(): array {
		$buffer                              = $this->__queued_component_validators;
		$this->__queued_component_validators = array();
		return $buffer;
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
		$field_key       = $this->base_options->normalize_schema_key($field_id);
		$defaults        = $this->components->get_defaults_for($component);
		$manifestContext = is_array($defaults['context'] ?? null) ? $defaults['context'] : array();
		// Merge manifest defaults with field-specific context; field context takes precedence
		$context = array_merge($manifestContext, $field_context);

		$sanitizer_factories = $this->components->sanitizer_factories();
		$factory             = $sanitizer_factories[$component] ?? null;
		if (!is_callable($factory)) {
			// Sanitizers are optional – silently skip components without one
			return;
		}

		$sanitizer_instance = $factory();
		$sanitizer_callable = function($value, callable $emitNotice) use ($sanitizer_instance, $context): mixed {
			return $sanitizer_instance->sanitize($value, $context, $emitNotice);
		};

		$hadSchema                                         = $this->base_options->has_schema_key($field_key);
		$this->__queued_component_sanitizers[$field_key][] = $sanitizer_callable;
		$this->logger->debug(static::class . ': Component sanitizer queued pending schema', array(
			'field_id'          => $field_id,
			'component'         => $component,
			'schema_registered' => $hadSchema,
		));
	}

	/**
	 * Retrieve and clear queued sanitizers awaiting schema registration.
	 *
	 * @internal Used by settings flows to register bucketed schema fragments.
	 *
	 * @return array<string, array<int, callable>>
	 */
	protected function _drain_queued_component_sanitizers(): array {
		$buffer                              = $this->__queued_component_sanitizers;
		$this->__queued_component_sanitizers = array();
		return $buffer;
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
		$storage       = $options->get_storage_context();
		$cacheKeyParts = array(
			$options->get_main_option_name(),
			$storage->scope?->value ?? 'site',
		);

		// Add scope-specific identifiers only when needed
		if ($storage->scope === OptionScope::Blog && $storage->blog_id !== null) {
			$cacheKeyParts[] = (string) $storage->blog_id;
		} elseif ($storage->scope === OptionScope::User && $storage->user_id !== null) {
			$cacheKeyParts[] = (string) $storage->user_id;
		}

		$cacheKey = implode('|', $cacheKeyParts);

		if (isset($this->__schema_bundle_cache[$cacheKey])) {
			$this->logger->debug('forms.schema_bundle.cache_hit', array(
				'key'    => $cacheKey,
				'intent' => $context['intent'] ?? 'none',
			));
			return $this->__schema_bundle_cache[$cacheKey];
		}

		$schemaInternal = $options->__get_schema_internal();
		$defaults       = array();
		foreach ($schemaInternal as $normalizedKey => $entry) {
			if (is_array($entry) && array_key_exists('default', $entry)) {
				$defaults[$normalizedKey] = array('default' => $entry['default']);
			}
		}

		$session = $this->get_form_session();
		if ($session === null) {
			$this->_start_form_session();
			$session = $this->get_form_session();
		}

		// Assemble bucketed schema with queued validators/sanitizers in one call
		$bucketedSchema   = array();
		$metadata         = array();
		$queuedValidators = array();
		$queuedSanitizers = array();

		if ($session !== null) {
			$assembled        = $this->_assemble_initial_bucketed_schema($session);
			$bucketedSchema   = $assembled['schema'];
			$metadata         = $assembled['metadata'];
			$queuedValidators = $assembled['queued_validators'];
			$queuedSanitizers = $assembled['queued_sanitizers'];
		}

		$bundle = array(
			'schema'            => $schemaInternal,
			'defaults'          => $defaults,
			'bucketed_schema'   => $bucketedSchema,
			'metadata'          => $metadata,
			'queued_validators' => $queuedValidators,
			'queued_sanitizers' => $queuedSanitizers,
		);

		$this->__schema_bundle_cache[$cacheKey] = $bundle;
		$this->logger->debug('forms.schema_bundle.cached', array(
			'key'                   => $cacheKey,
			'schema_keys'           => array_keys($schemaInternal),
			'default_count'         => count($defaults),
			'bucketed_count'        => count($bucketedSchema),
			'queued_validator_keys' => array_keys($queuedValidators),
			'queued_sanitizer_keys' => array_keys($queuedSanitizers),
		));

		return $bundle;
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
		$merged           = array();
		$metadata         = $bundle['metadata']          ?? array();
		$queuedValidators = $bundle['queued_validators'] ?? array();
		$queuedSanitizers = $bundle['queued_sanitizers'] ?? array();

		// Layer 1: Start with bucketed schema (component validators)
		if (!empty($bundle['bucketed_schema'])) {
			$merged = $bundle['bucketed_schema'];
		}

		// Layer 2: Merge in schema-level entries (developer validators)
		// Schema validators are added to the 'schema' bucket, not replacing component validators
		if (!empty($bundle['schema'])) {
			foreach ($bundle['schema'] as $key => $entry) {
				if (!isset($merged[$key])) {
					$merged[$key] = $entry;
				} else {
					// Merge the schema entry buckets
					$merged[$key] = $this->_merge_schema_entry_buckets($merged[$key], $entry);
				}
			}
		}

		// Layer 3: Defaults only fill missing keys (for seeding, not validation override)
		// We keep defaults separate for seeding purposes
		$defaultsForSeeding = $bundle['defaults'] ?? array();
		if (!empty($defaultsForSeeding)) {
			foreach ($defaultsForSeeding as $key => $entry) {
				if (!isset($merged[$key])) {
					// Key not in merged schema - add it with default only
					$merged[$key] = $entry;
				} elseif (!array_key_exists('default', $merged[$key]) && array_key_exists('default', $entry)) {
					// Merged entry exists but has no default - add the default
					$merged[$key]['default'] = $entry['default'];
				}
			}
		}

		$this->logger->debug('forms.schema_bundle.merged', array(
			'bucketed_count' => count($bundle['bucketed_schema'] ?? array()),
			'schema_count'   => count($bundle['schema'] ?? array()),
			'defaults_count' => count($defaultsForSeeding),
			'merged_count'   => count($merged),
		));

		return array(
			'merged_schema'        => $merged,
			'metadata'             => $metadata,
			'queued_validators'    => $queuedValidators,
			'queued_sanitizers'    => $queuedSanitizers,
			'defaults_for_seeding' => $defaultsForSeeding,
		);
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
		$merged = $existing;

		// Merge default (incoming takes precedence if both have it)
		if (array_key_exists('default', $incoming)) {
			$merged['default'] = $incoming['default'];
		}

		// Merge sanitize buckets
		if (isset($incoming['sanitize']) && is_array($incoming['sanitize'])) {
			if (!isset($merged['sanitize']) || !is_array($merged['sanitize'])) {
				$merged['sanitize'] = array('component' => array(), 'schema' => array());
			}
			foreach (array('component', 'schema') as $bucket) {
				if (isset($incoming['sanitize'][$bucket]) && is_array($incoming['sanitize'][$bucket])) {
					$merged['sanitize'][$bucket] = array_merge(
						$merged['sanitize'][$bucket] ?? array(),
						$incoming['sanitize'][$bucket]
					);
				}
			}
		}

		// Merge validate buckets
		if (isset($incoming['validate']) && is_array($incoming['validate'])) {
			if (!isset($merged['validate']) || !is_array($merged['validate'])) {
				$merged['validate'] = array('component' => array(), 'schema' => array());
			}
			foreach (array('component', 'schema') as $bucket) {
				if (isset($incoming['validate'][$bucket]) && is_array($incoming['validate'][$bucket])) {
					$merged['validate'][$bucket] = array_merge(
						$merged['validate'][$bucket] ?? array(),
						$incoming['validate'][$bucket]
					);
				}
			}
		}

		// Merge context if present
		if (isset($incoming['context']) && is_array($incoming['context'])) {
			$merged['context'] = array_merge($merged['context'] ?? array(), $incoming['context']);
		}

		return $merged;
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
		$bucketedSchema = array();
		$metadata       = array();

		// Session-scoped memoization – catalogue is fetched once and reused for all merge calls.
		// Cache clears naturally when the trait instance is garbage collected.
		if ($this->__catalogue_cache === null) {
			$this->__catalogue_cache = $this->components->default_catalogue();
			$this->logger->debug(static::class . ': Catalogue fetched and cached', array(
				'component_count' => count($this->__catalogue_cache),
			));
		}
		$manifestCatalogue = $this->__catalogue_cache;
		$internalSchema    = $this->base_options->__get_schema_internal();

		foreach ($this->_get_registered_field_metadata() as $entry) {
			$field     = $entry['field'] ?? array();
			$fieldId   = isset($field['id']) ? (string) $field['id'] : '';
			$component = isset($field['component']) ? (string) $field['component'] : '';
			if ($fieldId === '' || $component === '') {
				continue;
			}

			$normalizedKey   = $this->base_options->normalize_schema_key($fieldId);
			$currentEntry    = $internalSchema[$normalizedKey] ?? null;
			$componentSchema = $field['schema']                ?? array();
			if (!is_array($componentSchema)) {
				$componentSchema = array();
			}

			$componentContextFromCatalogue = isset($manifestCatalogue[$component]['context']) && is_array($manifestCatalogue[$component]['context'])
				? $manifestCatalogue[$component]['context']
				: array();

			// When schema already exists, only merge defaults if component buckets remain empty.
			if (is_array($currentEntry)) {
				$sanitizeComponents = (array) ($currentEntry['sanitize']['component'] ?? array());
				$validateComponents = (array) ($currentEntry['validate']['component'] ?? array());
				if ($sanitizeComponents === array() || $validateComponents === array()) {
					$merged                         = $session->merge_schema_with_defaults($component, $currentEntry, $manifestCatalogue);
					$bucketedSchema[$normalizedKey] = $merged;
					$context                        = isset($merged['context']) && is_array($merged['context']) ? $merged['context'] : array();
					$componentType                  = (string) ($context['component_type'] ?? ($componentContextFromCatalogue['component_type'] ?? ''));
					if ($componentType === ComponentType::FormField->value) {
						$metadata[$normalizedKey]['requires_validator'] = true;
					}
				}
				continue;
			}

			$merged                         = $session->merge_schema_with_defaults($component, $componentSchema, $manifestCatalogue);
			$bucketedSchema[$normalizedKey] = $merged;

			$context       = isset($merged['context']) && is_array($merged['context']) ? $merged['context'] : array();
			$componentType = (string) ($context['component_type'] ?? ($componentContextFromCatalogue['component_type'] ?? ''));
			if ($componentType === ComponentType::FormField->value) {
				$metadata[$normalizedKey]['requires_validator'] = true;
			}
		}

		// Consume queued validators and sanitizers as part of assembly
		$queuedValidators = array();
		$queuedSanitizers = array();

		if ($bucketedSchema !== array()) {
			list($bucketedSchema, $queuedValidators) = $this->_consume_component_validator_queue($bucketedSchema);
			list($bucketedSchema, $queuedSanitizers) = $this->_consume_component_sanitizer_queue($bucketedSchema);
		}

		return array(
			'schema'            => $bucketedSchema,
			'metadata'          => $metadata,
			'queued_validators' => $queuedValidators,
			'queued_sanitizers' => $queuedSanitizers,
		);
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
		$drained = $this->_drain_queued_component_validators();
		if ($drained === array()) {
			return array($bucketedSchema, array());
		}

		$queuedForSchema = array();
		$matchedCounts   = array();
		$unmatchedKeys   = array();
		$schemaKeyLookup = array_fill_keys(array_keys($bucketedSchema), true);

		foreach ($drained as $normalizedKey => $validators) {
			if (!is_array($validators) || $validators === array()) {
				continue;
			}

			$validators = array_values($validators);
			if (isset($schemaKeyLookup[$normalizedKey])) {
				$count                           = count($validators);
				$queuedForSchema[$normalizedKey] = $validators;
				$matchedCounts[$normalizedKey]   = $count;
				$this->logger->debug(static::class . ': Component validator queue matched schema key', array(
					'normalized_key'  => $normalizedKey,
					'validator_count' => $count,
				));
				continue;
			}

			if (!isset($this->__queued_component_validators[$normalizedKey])) {
				$this->__queued_component_validators[$normalizedKey] = $validators;
			} else {
				$this->__queued_component_validators[$normalizedKey] = array_merge(
					(array) $this->__queued_component_validators[$normalizedKey],
					$validators
				);
			}
			$unmatchedKeys[] = $normalizedKey;
			$this->logger->debug(static::class . ': Component validator queue re-queued unmatched key', array(
				'normalized_key'  => $normalizedKey,
				'validator_count' => count($validators),
			));
		}

		$this->logger->debug(static::class . ': Component validator queue consumed', array(
			'schema_keys'    => array_keys($bucketedSchema),
			'queued_counts'  => $matchedCounts,
			'unmatched_keys' => $unmatchedKeys,
		));

		return array($bucketedSchema, $queuedForSchema);
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
		$drained = $this->_drain_queued_component_sanitizers();
		if ($drained === array()) {
			return array($bucketedSchema, array());
		}

		$queuedForSchema = array();
		$matchedCounts   = array();
		$unmatchedKeys   = array();
		$schemaKeyLookup = array_fill_keys(array_keys($bucketedSchema), true);

		foreach ($drained as $normalizedKey => $sanitizers) {
			if (!is_array($sanitizers) || $sanitizers === array()) {
				continue;
			}

			$sanitizers = array_values($sanitizers);
			if (isset($schemaKeyLookup[$normalizedKey])) {
				$count                           = count($sanitizers);
				$queuedForSchema[$normalizedKey] = $sanitizers;
				$matchedCounts[$normalizedKey]   = $count;
				$this->logger->debug(static::class . ': Component sanitizer queue matched schema key', array(
					'normalized_key'  => $normalizedKey,
					'sanitizer_count' => $count,
				));
				continue;
			}

			if (!isset($this->__queued_component_sanitizers[$normalizedKey])) {
				$this->__queued_component_sanitizers[$normalizedKey] = $sanitizers;
			} else {
				$this->__queued_component_sanitizers[$normalizedKey] = array_merge(
					(array) $this->__queued_component_sanitizers[$normalizedKey],
					$sanitizers
				);
			}
			$unmatchedKeys[] = $normalizedKey;
			$this->logger->debug(static::class . ': Component sanitizer queue re-queued unmatched key', array(
				'normalized_key'  => $normalizedKey,
				'sanitizer_count' => count($sanitizers),
			));
		}

		$this->logger->debug(static::class . ': Component sanitizer queue consumed', array(
			'schema_keys'    => array_keys($bucketedSchema),
			'queued_counts'  => $matchedCounts,
			'unmatched_keys' => $unmatchedKeys,
		));

		return array($bucketedSchema, $queuedForSchema);
	}

	// -- File Upload Utilities --

	/**
	 * Check if a container (page/collection) contains file upload fields.
	 *
	 * @param string $container_id The container ID to check.
	 * @return bool True if the container has file upload fields.
	 */
	protected function _container_has_file_uploads(string $container_id): bool {
		// Check fields registered for this container
		$container_fields = $this->fields[$container_id] ?? array();
		foreach ($container_fields as $section_id => $fields) {
			foreach ($fields as $field) {
				$component = $field['component'] ?? '';
				if ($component === 'fields.file-upload') {
					return true;
				}
			}
		}

		// Check groups registered for this container
		$container_groups = $this->groups[$container_id] ?? array();
		foreach ($container_groups as $section_id => $groups) {
			foreach ($groups as $group_id => $group) {
				$group_fields = $group['fields'] ?? array();
				foreach ($group_fields as $field) {
					$component = $field['component'] ?? '';
					if ($component === 'fields.file-upload') {
						return true;
					}
				}
			}
		}

		return false;
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
		$processed = array();

		// Check if there are any files uploaded for our option
		if (!isset($_FILES[$this->main_option]) || !is_array($_FILES[$this->main_option])) {
			return $processed;
		}

		$optionFiles = $_FILES[$this->main_option];

		// Process each file field
		foreach ($optionFiles['name'] as $fieldKey => $fileName) {
			// Skip if no file was uploaded for this field
			if (empty($fileName) || $optionFiles['error'][$fieldKey] === UPLOAD_ERR_NO_FILE) {
				continue;
			}

			// Skip if there was an upload error
			if ($optionFiles['error'][$fieldKey] !== UPLOAD_ERR_OK) {
				$this->logger->warning('FormsBaseTrait._process_uploaded_files: Upload error', array(
					'field'      => $fieldKey,
					'error_code' => $optionFiles['error'][$fieldKey],
				));
				continue;
			}

			// Reconstruct the file array for this specific field
			$file = array(
				'name'     => $optionFiles['name'][$fieldKey],
				'type'     => $optionFiles['type'][$fieldKey],
				'tmp_name' => $optionFiles['tmp_name'][$fieldKey],
				'error'    => $optionFiles['error'][$fieldKey],
				'size'     => $optionFiles['size'][$fieldKey],
			);

			// Process the upload using WordPress
			$result = $this->_process_single_file_upload($file);

			if ($result !== null) {
				$processed[$fieldKey] = $result;
				$this->logger->debug('FormsBaseTrait._process_uploaded_files: File processed', array(
					'field'  => $fieldKey,
					'result' => $result,
				));
			}
		}

		return $processed;
	}

	/**
	 * Process a single file upload using WordPress functions.
	 *
	 * @param array<string,mixed> $file The file data from $_FILES.
	 * @return array<string,mixed>|null The processed file data or null on failure.
	 */
	protected function _process_single_file_upload(array $file): ?array {
		// Verify the file exists and is an uploaded file
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return null;
		}

		// Use WordPress upload handler
		$overrides = array(
			'test_form' => false, // We've already verified the form
			'test_type' => true,  // Check MIME type
		);

		$result = $this->_do_wp_handle_upload($file, $overrides);

		if (isset($result['error'])) {
			$this->logger->warning('FormsBaseTrait._process_single_file_upload: Upload failed', array(
				'error' => $result['error'],
				'file'  => $file['name'],
			));
			return null;
		}

		// Build the file data array
		$fileData = array(
			'url'      => $result['url'],
			'file'     => $result['file'],
			'type'     => $result['type'],
			'filename' => $this->_do_sanitize_file_name($file['name']),
		);

		// Optionally create a media library attachment
		$attachmentId = $this->_create_media_attachment($result);
		if ($attachmentId !== null) {
			$fileData['attachment_id'] = $attachmentId;
		}

		return $fileData;
	}

	/**
	 * Create a media library attachment for an uploaded file.
	 *
	 * @param array<string,mixed> $uploadResult The result from wp_handle_upload.
	 * @return int|null The attachment ID or null on failure.
	 */
	protected function _create_media_attachment(array $uploadResult): ?int {
		$filePath = $uploadResult['file'];
		$fileUrl  = $uploadResult['url'];
		$fileType = $uploadResult['type'];

		$attachment = array(
			'guid'           => $fileUrl,
			'post_mime_type' => $fileType,
			'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filePath)),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachmentId = $this->_do_wp_insert_attachment($attachment, $filePath);

		if ($this->_do_is_wp_error($attachmentId)) {
			$this->logger->warning('FormsBaseTrait._create_media_attachment: Failed to create attachment', array(
				'error' => $attachmentId->get_error_message(),
			));
			return null;
		}

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachmentData = $this->_do_wp_generate_attachment_metadata($attachmentId, $filePath);
		$this->_do_wp_update_attachment_metadata($attachmentId, $attachmentData);

		return $attachmentId;
	}
}
