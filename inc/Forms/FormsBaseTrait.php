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
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;

/**
 * Shared functionality for form-based classes.
 */
trait FormsBaseTrait {
	// Core form infrastructure properties

	protected string $main_option;
	protected ?array $pending_values = null;
	protected ComponentManifest $components;
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
	/** @var array<string, array<string, array{title:string, description_cb:?callable, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $sections = array();
	/** @var array<string, array<string, array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int, before:?callable, after:?callable}>>> */
	protected array $fields = array();
	/** @var array<string, array<string, array{group_id:string, fields:array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $groups = array();
	/** @var array<string, array{zone_id:string, before:?callable, after:?callable, controls: array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int}>}> */
	protected array $submit_controls = array();


	// Template override system removed - now handled by FormsTemplateOverrideResolver in FormsServiceSession

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;


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
	 * @param array<string,mixed>|null $context Optional resolution context.
	 *
	 * @return RegisterOptions
	 */
	public function resolve_options(?array $context = null): RegisterOptions {
		$resolved = $this->_resolve_context($context ?? array());
		return $this->base_options->with_context($resolved['storage']);
	}

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
	abstract public function render(string $id_slug, ?array $context = null): void;

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
	 * Get the FormsServiceSession instance for direct access to template resolution.
	 *
	 * @return FormsServiceSession|null The FormsServiceSession instance or null if not started
	 */
	public function get_form_session(): ?FormsServiceSession {
		return $this->form_session;
	}

	// Protected methods

	/**
	 * Handle context update (eg AdminSettings page, UserSettings collection, etc) from builders.
	 *
	 * @param string $type The type of update
	 * @param array $data context data to update
	 * @return void
	 */
	protected abstract function _handle_context_update(string $type, array $data): void;

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

			$context['field_id']  = $control_id;
			$context['_field_id'] = $control_id;
			$context['label']     = $label;
			$context['_label']    = $label;

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

		$this->submit_controls[$container_id]['controls'] = $normalized;
		if (!empty($normalized)) {
			$this->logger->debug('forms.submit_controls.controls.updated', array(
				'container_id' => $container_id,
				'zone_id'      => $zone_id,
				'count'        => count($normalized),
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
		);
		$this->logger->debug('settings.builder.section.updated', array(
			'container_id'       => $container_id,
			'section_id'         => $section_id,
			'heading'            => $this->sections[$container_id][$section_id]['title'],
			'has_description_cb' => $this->sections[$container_id][$section_id]['description_cb'] !== null,
			'order'              => $this->sections[$container_id][$section_id]['order'],
			'has_before'         => $this->sections[$container_id][$section_id]['before'] !== null,
			'has_after'          => $this->sections[$container_id][$section_id]['after']  !== null,
		));
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
		$this->logger->debug('settings.builder.section.metadata', array(
			'container_id'       => $container_id,
			'section_id'         => $section_id,
			'heading'            => $section['title'],
			'has_description_cb' => $section['description_cb'] !== null,
			'order'              => $section['order'],
			'has_before'         => $section['before'] !== null,
			'has_after'          => $section['after']  !== null,
		));
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
			// Inject component validators automatically for new fields only
			$this->_inject_component_validators($field_id, $component);

			$field_entry['index'] = $this->__field_index++;
			$fields[]             = $field_entry;
		}

		usort($fields, function(array $a, array $b): int {
			return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
		});
		$this->fields[$container_id][$section_id] = array_values($fields);
		$this->logger->debug('settings.builder.field.updated', array(
			'container_id'      => $container_id,
			'section_id'        => $section_id,
			'field_id'          => $field_entry['id'],
			'label'             => $field_entry['label'],
			'component'         => $field_entry['component'],
			'order'             => $field_entry['order'],
			'component_context' => $field_entry['component_context'],
		));
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
			'style'    => (string) ($group_data['style'] ?? 'bordered'),
			'required' => (bool) ($group_data['required'] ?? false),
			'index'    => $this->__group_index++,
		);
		$this->logger->debug('settings.builder.group.updated', array(
			'container_id' => $container_id,
			'section_id'   => $section_id,
			'group_id'     => $group_id,
			'heading'      => $this->groups[$container_id][$section_id][$group_id]['title'],
			'order'        => $this->groups[$container_id][$section_id][$group_id]['order'],
			'has_before'   => $this->groups[$container_id][$section_id][$group_id]['before'] !== null,
			'has_after'    => $this->groups[$container_id][$section_id][$group_id]['after']  !== null,
			'fields'       => array_column($this->groups[$container_id][$section_id][$group_id]['fields'], 'id'),
		));
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
			$this->_inject_component_validators($field_id, $component);

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
		$this->logger->debug('settings.builder.group_field.updated', array(
			'container_id'      => $container_id,
			'section_id'        => $section_id,
			'group_id'          => $group_id,
			'field_id'          => $field_id,
			'label'             => $field_data['label'] ?? '',
			'component'         => $component,
			'order'             => $orderValue,
			'component_context' => $context,
		));
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

		$this->groups[$container_id][$section_id][$group_id]['title']    = (string) $title;
		$this->groups[$container_id][$section_id][$group_id]['before']   = $group_data['before'] ?? null;
		$this->groups[$container_id][$section_id][$group_id]['after']    = $group_data['after']  ?? null;
		$this->groups[$container_id][$section_id][$group_id]['order']    = (int) ($group_data['order'] ?? 0);
		$this->groups[$container_id][$section_id][$group_id]['style']    = (string) ($group_data['style'] ?? 'bordered');
		$this->groups[$container_id][$section_id][$group_id]['required'] = (bool) ($group_data['required'] ?? false);
		$this->logger->debug('settings.builder.group.metadata', array(
			'container_id' => $container_id,
			'section_id'   => $section_id,
			'group_id'     => $group_id,
			'heading'      => $this->groups[$container_id][$section_id][$group_id]['title'],
			'order'        => $this->groups[$container_id][$section_id][$group_id]['order'],
			'style'        => $this->groups[$container_id][$section_id][$group_id]['style'],
			'required'     => $this->groups[$container_id][$section_id][$group_id]['required'],
			'has_before'   => $this->groups[$container_id][$section_id][$group_id]['before'] !== null,
			'has_after'    => $this->groups[$container_id][$section_id][$group_id]['after']  !== null,
			'fields'       => array_column($this->groups[$container_id][$section_id][$group_id]['fields'], 'id'),
		));
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
			$this->logger->debug('settings.builder.template_override', array(
				'element_type' => $element_type,
				'element_id'   => $element_id,
				'overrides'    => $overrides,
			));
			return;
		}

		if ($zone_id !== '' || array_key_exists('callback', $data)) {
			$this->logger->debug('settings.builder.template_override', array(
				'element_type' => $element_type,
				'element_id'   => $element_id,
				'zone_id'      => $zone_id,
				'callback'     => array_key_exists('callback', $data) && is_callable($data['callback']) ? 'registered' : (array_key_exists('callback', $data) && $data['callback'] === null ? 'cleared' : null),
			));
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
			$this->form_session  = $this->form_service->start_session($this->shared_assets);
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

	/**
	 * Sanitize a key for WordPress usage.
	 * This method should be implemented by classes that use WPWrappersTrait.
	 *
	 * @param string $key
	 * @return string
	 */
	abstract protected function _do_sanitize_key(string $key): string;


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
		$prepared_sections = array();
		$groups_map        = $this->groups[$id_slug] ?? array();
		$fields_map        = $this->fields[$id_slug] ?? array();

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

			// Combine groups and fields
			$items = array();
			foreach ($groups as $group) {
				$group_fields = $group['fields'];
				usort($group_fields, function ($a, $b) {
					return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
				});

				$group_items = array();
				foreach ($group_fields as $group_field) {
					$field_before = isset($group_field['before']) ? $this->_render_callback_output($group_field['before'], array(
						'field_id'     => $group_field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => $group['group_id'] ?? '',
						'values'       => $values,
					)) : null;
					$field_after = isset($group_field['after']) ? $this->_render_callback_output($group_field['after'], array(
						'field_id'     => $group_field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => $group['group_id'] ?? '',
						'values'       => $values,
					)) : null;
					$group_items[] = array(
						'field'  => $group_field,
						'before' => $field_before,
						'after'  => $field_after,
					);
				}

				$items[] = array(
					'type'     => 'group',
					'group_id' => $group['group_id'] ?? '',
					'before'   => $this->_render_callback_output($group['before'] ?? null, array(
						'group_id'     => $group['group_id'] ?? '',
						'section_id'   => $section_id,
						'container_id' => $id_slug,
						'fields'       => $group_fields,
						'values'       => $values,
					)),
					'after' => $this->_render_callback_output($group['after'] ?? null, array(
						'group_id'     => $group['group_id'] ?? '',
						'section_id'   => $section_id,
						'container_id' => $id_slug,
						'fields'       => $group_fields,
						'values'       => $values,
					)),
					'items' => $group_items,
				);
			}
			foreach ($fields as $field) {
				$field_before = isset($field['before']) ? $this->_render_callback_output($field['before'], array(
					'field_id'     => $field['id'] ?? '',
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) : null;
				$field_after = isset($field['after']) ? $this->_render_callback_output($field['after'], array(
					'field_id'     => $field['id'] ?? '',
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) : null;
				$items[] = array(
					'type'   => 'field',
					'field'  => $field,
					'before' => $field_before,
					'after'  => $field_after,
				);
			}

			// Sort items (groups first, then fields)
			usort($items, function ($a, $b) {
				return ($a['type'] === 'group' ? 0 : 1) <=> ($b['type'] === 'group' ? 0 : 1);
			});

			$prepared_sections[] = array(
				'container_id'   => $id_slug,
				'section_id'     => $section_id,
				'title'          => (string) $meta['title'],
				'description_cb' => $meta['description_cb'] ?? null,
				'before'         => $this->_render_callback_output($meta['before'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)),
				'after' => $this->_render_callback_output($meta['after'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)),
				'items' => $items,
			);
		}

		$sectionComponent = $this->views->render('section', array(
			'sections'       => $prepared_sections,
			'field_renderer' => function(array $field_item) use ($values): string {
				return $this->_render_default_field_wrapper($field_item, $values);
			},
		));

		if (!$sectionComponent instanceof ComponentRenderResult) {
			throw new UnexpectedValueException('Section template must return a ComponentRenderResult instance.');
		}

		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$this->form_session->assets()->ingest($sectionComponent);

		return $sectionComponent->markup;
	}

	protected function _render_callback_output(?callable $callback, array $context): ?string {
		if ($callback === null) {
			return null;
		}

		if (!is_callable($callback)) {
			$this->logger->warning('FormsBaseTrait: Callback provided is not callable', array('context_keys' => array_keys($context)));
			return null;
		}

		try {
			return (string) $callback($context);
		} catch (\Throwable $e) {
			$this->logger->error('FormsBaseTrait: Callback execution failed', array(
				'context_keys' => array_keys($context),
				'exception'    => $e,
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

		$context = $field['component_context'] ?? array();
		if (!is_array($context)) {
			$this->logger->error( static::class . ': field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(static::class . ': field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}
		$context['field_id']  = $field_id;
		$context['_field_id'] = $field_id;
		$context['label']     = $label;
		$context['_label']    = $label;

		// Get messages for this field
		$field_messages = $this->message_handler->get_messages_for_field($field_id);

		// Prepare field configuration
		$field_config = array(
			'field_id'          => $field_id,
			'component'         => $component,
			'label'             => $label,
			'component_context' => $context
		);

		// Use FormElementRenderer for complete field processing with wrapper
		try {
			if ($this->form_session === null) {
				$this->_start_form_session();
			}

			$field_context = $this->field_renderer->prepare_field_context(
				$field_config,
				$values,
				array($field_id => $field_messages)
			);
			if (isset($field_item['before'])) {
				$field_context['before'] = $field_item['before'];
			}
			if (isset($field_item['after'])) {
				$field_context['after'] = $field_item['after'];
			}

			// Let FormElementRenderer handle both component rendering and wrapper application
			return $this->field_renderer->render_field_with_wrapper(
				$component,
				$field_id,
				$label,
				$field_context,
				$values,
				'field-wrapper',
				$this->form_session
			);
		} catch (\Throwable $e) {
			$this->logger->error(static::class . ': Field rendering failed', array(
				'field_id'  => $field_id,
				'component' => $component,
				'exception' => $e
			));
			// @TODO will this break table based layouts?
			return $this->_render_default_field_wrapper_warning($e->getMessage());
		}
	}

	/**
	 * Context specific render a field wrapper warning.
	 * Uses FormsServiceSession to render error messages with proper template resolution.
	 *
	 * @param string $message The error message
	 * @return string Rendered field HTML.
	 */
	private function _render_default_field_wrapper_warning(string $message): string {
		// Use FormsServiceSession to render error with proper template resolution
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		// Render error using the field-wrapper template with error context
		return $this->form_session->render_component('field-wrapper', array(
			'field_id'      => 'error',
			'label'         => 'Error',
			'content'       => esc_html($message),
			'is_error'      => true,
			'error_message' => $message
		));
	}

	/**
	 * Discover and inject component validators for a field.
	 *
	 * @todo Does ComponetManifest provide a per Component registery of associated validators?
	 *
	 * @param string $field_id Field identifier
	 * @param string $component Component name
	 * @return void
	 */
	protected function _inject_component_validators(string $field_id, string $component): void {
		// Get component validator factories from ComponentManifest
		$validator_factories = $this->components->validator_factories();

		if (isset($validator_factories[$component])) {
			$validator_factory = $validator_factories[$component];

			// Create the validator instance
			if (is_callable($validator_factory)) {
				$validator_instance = $validator_factory();

				// Create a callable wrapper that adapts ValidatorInterface to RegisterOptions signature
				$validator_callable = function($value, callable $emitWarning) use ($validator_instance): bool {
					return $validator_instance->validate($value, array(), $emitWarning);
				};

				// Register the validator as a component-sourced validator
				$this->base_options->add_component_validator($field_id, $validator_callable);

				$this->logger->debug(static::class . ': Component validator injected', array(
					'field_id'  => $field_id,
					'component' => $component
				));
			}
		}
	}

	/**
	 * Resolve context for the specific implementation.
	 * Each class resolves context differently based on their scope.
	 *
	 * @param array<string,mixed> $context
	 * @return array
	 */
	abstract protected function _resolve_context(array $context): array;
}
