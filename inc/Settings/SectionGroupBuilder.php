<?php
/**
 * SectionGroupBuilder: Fluent builder for grouped fields within a section.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;

final class SectionGroupBuilder {
	private SectionBuilder $sectionBuilder;
	private string $collection_slug;
	private string $section_id;
	private string $group_id;
	private string $title;
	/** @var callable */
	private $onAddGroup;
	/** @var array<int, array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int}> */
	private array $fields = array();
	/** @var callable|null */
	private $before;
	/** @var callable|null */
	private $after;
	private ?int $order;
	private bool $committed = false;
	/** @var array<string, string> */
	private array $template_overrides = array();

	public function __construct(
		SectionBuilder $sectionBuilder,
		string $collection_slug,
		string $section_id,
		string $group_id,
		string $title,
		callable $onAddGroup,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		$this->sectionBuilder  = $sectionBuilder;
		$this->collection_slug = $collection_slug;
		$this->section_id      = $section_id;
		$this->group_id        = $group_id;
		$this->title           = $title;
		$this->onAddGroup      = $onAddGroup;
		$this->before          = $before;
		$this->after           = $after;
		$this->order           = $order;
	}

	/**
	 * Commit buffered data and return to the section builder.
	 */
	public function __destruct() {
		$this->_commit();
	}

	/**
	 * Define a field within this group.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array $component_context The component context.
	 * @param int|null $order The field order.
	 * @param string|null $field_template Optional field wrapper template override.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function field(string $field_id, string $label, string $component, array $component_context = array(), ?int $order = null, ?string $field_template = null): self {
		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Grouped field "%s" requires a component alias.', $field_id));
		}
		if (!is_array($component_context)) {
			throw new \InvalidArgumentException(sprintf('Grouped field "%s" must provide an array component_context.', $field_id));
		}

		$this->fields[] = array(
		    'id'                => $field_id,
		    'label'             => $label,
		    'component'         => $component,
		    'component_context' => $component_context,
		    'order'             => ($order !== null ? (int) $order : 0),
		);

		// Apply field-level template override if provided
		if ($field_template !== null) {
			if (method_exists($this->sectionBuilder, 'get_settings')) {
				$settings = $this->sectionBuilder->get_settings();
				if ($settings instanceof \Ran\PluginLib\Settings\SettingsInterface) {
					$settings->set_field_template_overrides($field_id, array('field-wrapper' => $field_template));
				}
			}
		}

		return $this;
	}

	/**
	 * Define a field within this group using a builder definition.
	 *
	 * @param BuilderDefinitionInterface $definition The builder definition.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function definition(BuilderDefinitionInterface $definition): self {
		$field = $definition->to_array();
		if (!isset($field['id'], $field['label'], $field['component'])) {
			throw new \InvalidArgumentException(sprintf('Grouped field definition "%s" is missing required metadata.', $definition->get_id()));
		}
		$component = trim((string) $field['component']);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Grouped field definition "%s" must provide a component alias.', $definition->get_id()));
		}
		$context = array();
		if (isset($field['component_context'])) {
			if (!is_array($field['component_context'])) {
				throw new \InvalidArgumentException(sprintf('Grouped field definition "%s" component_context must be an array.', $definition->get_id()));
			}
			$context = $field['component_context'];
		}

		$this->fields[] = array(
		    'id'                => (string) $field['id'],
		    'label'             => (string) $field['label'],
		    'component'         => $component,
		    'component_context' => $context,
		    'order'             => isset($field['order']) ? (int) $field['order'] : 0,
		);

		return $this;
	}

	/**
	 * Set the before callback.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function before(?callable $before): self {
		$this->before = $before;
		return $this;
	}

	/**
	 * Set the after callback.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function after(?callable $after): self {
		$this->after = $after;
		return $this;
	}

	/**
	 * Set the order.
	 *
	 * @param int|null $order The order.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function order(?int $order): self {
		$this->order = $order;
		return $this;
	}

	/**
	 * Set the group template for this specific group.
	 *
	 * @param string $template_key The template key to use for group wrapper.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function group_template(string $template_key): self {
		$this->template_overrides['group'] = $template_key;
		return $this;
	}

	/**
	 * Set the default field template for all fields in this group.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return SectionGroupBuilder The SectionGroupBuilder instance.
	 */
	public function field_template(string $template_key): self {
		$this->template_overrides['field-wrapper'] = $template_key;
		return $this;
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function end_group(): SectionBuilder {
		$this->_commit();
		return $this->sectionBuilder;
	}

	/**
	 * Commit buffered data and return to the collection builder.
	 *
	 * @return CollectionBuilderInterface The CollectionBuilder instance.
	 */
	public function end_section(): CollectionBuilderInterface {
		$this->_commit();
		return $this->sectionBuilder->end_section();
	}

	/**
	 * Commit buffered data.
	 */
	private function _commit(): void {
		if ($this->committed) {
			return;
		}

		// Apply template overrides before committing
		$this->_apply_template_overrides();

		($this->onAddGroup)(
			$this->collection_slug,
			$this->section_id,
			$this->group_id,
			$this->title,
			$this->fields,
			$this->before,
			$this->after,
			$this->order
		);
		$this->fields    = array();
		$this->before    = null;
		$this->after     = null;
		$this->committed = true;
	}

	/**
	 * Apply template overrides to the Settings instance.
	 */
	private function _apply_template_overrides(): void {
		if (!empty($this->template_overrides)) {
			if (method_exists($this->sectionBuilder, 'get_settings')) {
				$settings = $this->sectionBuilder->get_settings();
				if ($settings instanceof \Ran\PluginLib\Settings\SettingsInterface) {
					$settings->set_group_template_overrides($this->group_id, $this->template_overrides);
				}
			}
		}
	}
}
