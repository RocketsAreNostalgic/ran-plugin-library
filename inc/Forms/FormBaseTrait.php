<?php
/**
 * FormBaseTrait: Shared functionality for form-based classes.
 *
 * This trait provides common functionality that is identical between AdminSettings,
 * UserSettings, and future form classes. It only includes methods that have been
 * verified to be truly identical in implementation.
 *
 * @package Ran\PluginLib\Forms
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;

/**
 * Shared functionality for form-based classes.
 *
 * Provides only methods that are verified to be identical between classes:
 * - Template override getters/setters (section, group, field, default)
 * - Form session management
 * - Message handling
 * - Component validator injection
 * - RegisterOptions resolution
 */
trait FormBaseTrait {
	// Core form infrastructure properties

	protected string $main_option;
	protected ?array $pending_values = null;
	protected ComponentManifest $components;
	protected FormService $form_service;
	protected FormElementRenderer $field_renderer;
	protected FormMessageHandler $message_handler;
	protected ?FormServiceSession $form_session = null; //
	protected Logger $logger;
	protected RegisterOptions $base_options;


	// Settings structure: sections, fields, and groups organized by container

	/** @var array<string, array<string, array{title:string, description_cb:?callable, order:int, index:int}>> */
	protected array $sections = array();
	/** @var array<string, array<string, array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>>> */
	protected array $fields = array();
	/** @var array<string, array<string, array{group_id:string, fields:array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $groups = array();


	// Template override system: hierarchical template customization

	/** @var array<string, array<string, string>> */
	protected array $default_template_overrides = array();
	/** @var array<string, array<string, string>> */
	protected array $root_template_overrides = array();
	/** @var array<string, array<string, string>> */
	protected array $section_template_overrides = array();
	/** @var array<string, array<string, string>> */
	protected array $group_template_overrides = array();
	/** @var array<string, array<string, string>> */
	protected array $field_template_overrides = array();

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;


	/** ✅
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

	/** ✅
	 * Boot admin: register root, sections, fields, templates.
	 */
	abstract public function boot(): void;

	/** ✅
	 * Render a registered root template.
	 */
	abstract protected function render(): void;

	/** ✅
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

	/** ✅
	 * Set default template overrides for this AdminSettings instance.
	 *
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_default_template_overrides(array $template_overrides): void {
		$this->default_template_overrides = array_merge($this->default_template_overrides, $template_overrides);
		$this->logger->debug('AdminSettings: Default template overrides set', array(
			'overrides' => array_keys($template_overrides)
		));
	}

	/** ✅
	 * Get default template overrides for this AdminSettings instance.
	 *
	 * @return array<string, string>
	 */
	public function get_default_template_overrides(): array {
		return $this->default_template_overrides;
	}

	/** ✅
	 * Set template overrides for the root container (page for AdminSettings).
	 *
	 * @param string $root_id_slug The root container ID (page slug).
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */

	public function set_root_template_overrides(string $root_id_slug, array $template_overrides): void {
		$this->root_template_overrides[$root_id_slug] = $template_overrides;
		$this->logger->debug( static::class . ': Root template overrides set', array(
			'id_slug'   => $root_id_slug,
			'overrides' => array_keys($template_overrides)
		));
	}

	/** ✅
	 * Get template overrides for the root container (page for AdminSettings).
	 *
	 * @param string $root_id_slug The root container ID (page slug).
	 *
	 * @return array<string, string>
	 */
	public function get_root_template_overrides(string $root_id_slug): array {
		return $this->root_template_overrides[$root_id_slug] ?? array();
	}

	/** ✅
	 * Set template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $template_overrides): void {
		$this->section_template_overrides[$section_id] = $template_overrides;
		$this->logger->debug(static::class . ': Section template overrides set', array(
			'section_id' => $section_id,
			'overrides'  => array_keys($template_overrides)
		));
	}

	/** ✅
	 * Get template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 *
	 * @return array<string, string>
	 */
	public function get_section_template_overrides(string $section_id): array {
		return $this->section_template_overrides[$section_id] ?? array();
	}

	/** ✅
	 * Set template overrides for a specific group.
	 *
	 * @param string $group_id The group ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_group_template_overrides(string $group_id, array $template_overrides): void {
		$this->group_template_overrides[$group_id] = $template_overrides;
		$this->logger->debug('AdminSettings: Group template overrides set', array(
			'group_id'  => $group_id,
			'overrides' => array_keys($template_overrides)
		));
	}

	/** ✅
	 * Get template overrides for a specific group.
	 *
	 * @param string $group_id The group ID.
	 *
	 * @return array<string, string>
	 */
	public function get_group_template_overrides(string $group_id): array {
		return $this->group_template_overrides[$group_id] ?? array();
	}

	/** ✅
	 * Set template overrides for a specific field.
	 *
	 * @param string $field_id The field ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_field_template_overrides(string $field_id, array $template_overrides): void {
		$this->field_template_overrides[$field_id] = $template_overrides;
		$this->logger->debug(static::class . ': Field template overrides set', array(
			'field_id'  => $field_id,
			'overrides' => array_keys($template_overrides)
		));
	}

	/** ✅
	 * Get template overrides for a specific field.
	 *
	 * @param string $field_id The field ID.
	 *
	 * @return array<string, string>
	 */
	public function get_field_template_overrides(string $field_id): array {
		return $this->field_template_overrides[$field_id] ?? array();
	}

	/** ✅
	 * Resolve template with hierarchical fallback.
	 *
	 * @param string $template_type The template type (e.g., 'page', 'section', 'group', 'field-wrapper').
	 * @param array<string, mixed> $context Resolution context containing field_id, section_id, page_slug, etc.
	 *
	 * @return string The resolved template key.
	 */
	public function resolve_template(string $template_type, array $context = array()): string {
		// 1. Check field-level override (highest priority)
		if (isset($context['field_id'])) {
			$field_overrides = $this->get_field_template_overrides($context['field_id']);
			if (isset($field_overrides[$template_type])) {
				$this->logger->debug(static::class . ': Template resolved via field override', array(
					'template_type' => $template_type,
					'template'      => $field_overrides[$template_type],
					'field_id'      => $context['field_id']
				));
				return $field_overrides[$template_type];
			}
		}

		// 2. Check group-level override
		if (isset($context['group_id'])) {
			$group_overrides = $this->get_group_template_overrides($context['group_id']);
			if (isset($group_overrides[$template_type])) {
				$this->logger->debug(static::class . ': Template resolved via group override', array(
					'template_type' => $template_type,
					'template'      => $group_overrides[$template_type],
					'group_id'      => $context['group_id']
				));
				return $group_overrides[$template_type];
			}
		}

		// 3. Check section-level override
		if (isset($context['section_id'])) {
			$section_overrides = $this->get_section_template_overrides($context['section_id']);
			if (isset($section_overrides[$template_type])) {
				$this->logger->debug(static::class . ': Template resolved via section override', array(
					'template_type' => $template_type,
					'template'      => $section_overrides[$template_type],
					'section_id'    => $context['section_id']
				));
				return $section_overrides[$template_type];
			}
		}

		// 4. Check page-level override
		if (isset($context['id_slug'])) {
			$page_overrides = $this->get_root_template_overrides($context['page_slug']);
			if (isset($page_overrides[$template_type])) {
				$this->logger->debug(static::class . ': Template resolved via page override', array(
					'template_type' => $template_type,
					'template'      => $page_overrides[$template_type],
					'page_slug'     => $context['page_slug']
				));
				return $page_overrides[$template_type];
			}
		}

		// 5. Check class instance defaults
		if (isset($this->default_template_overrides[$template_type])) {
			$this->logger->debug(static::class . ': Template resolved via class default', array(
				'template_type' => $template_type,
				'template'      => $this->default_template_overrides[$template_type]
			));
			return $this->default_template_overrides[$template_type];
		}

		// 6. System defaults (lowest priority)
		$system_default = $this->_get_system_default_template($template_type);
		$this->logger->debug(static::class . ': Template resolved via system default', array(
			'template_type' => $template_type,
			'template'      => $system_default
		));
		return $system_default;
	}

	// Protected

	/** ✅
	 * Start a new form session.
	 */
	protected function _start_form_session(): void {
		$this->form_session = $this->form_service->start_session();
	}

	/** ✅
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

	/** ✅
	 * Get system default template for a template type.
	 *
	 * @param string $template_type The template type.
	 *
	 * @return string The system default template key.
	 */
	abstract protected function _get_system_default_template(string $template_type): string;

	/** ✅
	 * Render the default page template markup.
	 *
	 * @param array<string,mixed> $context
	 * @return void
	 */
	abstract protected function _render_default_root(array $context):void;

	/** ✅
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
				$items[] = array(
					'type'   => 'group',
					'before' => $group['before'] ?? null,
					'after'  => $group['after']  ?? null,
					'fields' => $group_fields,
				);
			}
			foreach ($fields as $field) {
				$items[] = array('type' => 'field', 'field' => $field);
			}

			// Sort items (groups first, then fields)
			usort($items, function ($a, $b) {
				return ($a['type'] === 'group' ? 0 : 1) <=> ($b['type'] === 'group' ? 0 : 1);
			});

			$prepared_sections[] = array(
				'title'          => (string) $meta['title'],
				'description_cb' => $meta['description_cb'] ?? null,
				'items'          => $items,
			);
		}

		return $this->views->render('section', array(
			'sections'       => $prepared_sections,
			'group_renderer' => fn (array $group): string => $this->_render_default_group_wraper($group, $values),
			'field_renderer' => fn (array $field): string => $this->_render_default_field_wrapper($field, $values),
		));
	}

	/** ✅
	 * Render a single field wrappper
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 *
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper(array $field, array $values): string {
		if (empty($field)) {
			return '';
		}

		$field_id  = isset($field['id']) ? (string) $field['id'] : '';
		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';

		if ($component === '') {
			$this->logger->error(static::class . ': field missing component metadata.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(static::class . ': field "%s" requires a component alias.', $field_id ?: 'unknown'));
		}

		$context = $field['component_context'] ?? array();
		if (!is_array($context)) {
			$this->logger->error( static::class . ': field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(static::class . ': field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}

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
			$field_context = $this->field_renderer->prepare_field_context(
				$field_config,
				$values,
				array($field_id => $field_messages)
			);

			// Let FormElementRenderer handle both component rendering and wrapper application
			return $this->field_renderer->render_field_with_wrapper(
				$component,
				$field_id,
				$label,
				$field_context,
				$values,
				'field-wrapper' // FormElementRenderer will resolve via template overrides
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

	/** ✅
	 * Context specific fender a field wrapper warning.
	 * Can be customised for tables based layouts etc.
	 *
	 * @return string Rendered field HTML.
	 */
	abstract private function _render_default_field_wrapper_warning():string;

	/** ✅
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

				// Inject the validator at the beginning of the validation chain
				$this->base_options->prepend_validator($field_id, $validator_callable);

				$this->logger->debug(static::class . ': Component validator injected', array(
					'field_id'  => $field_id,
					'component' => $component
				));
			}
		}
	}

	/** ✅
	 * Augment the component context with the main option name.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $values
	 *
	 * @return array<string,mixed>
	 */
	protected function _augment_component_context(array $context, array $values): array {
		$context['_option'] = $this->main_option;
		return $context;
	}

	/** ✅
	 * Resolve context for the specific implementation.
	 * Each class resolves context differently based on their scope.
	 *
	 * @param array<string,mixed> $context
	 * @return array
	 */
	abstract protected function _resolve_context(array $context): array;
}
