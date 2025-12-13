<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

interface FormsStateStoreInterface {
	/**
	 * @return array<int, array{
	 *     container_id:string,
	 *     section_id:string,
	 *     group_id:?string,
	 *     field:array<string,mixed>,
	 *     group?:array<string,mixed>
	 * }>
	 */
	public function get_registered_field_metadata(): array;

	public function lookup_component_alias(string $field_id): ?string;

	public function has_submit_controls(string $container_id): bool;

	/**
	 * @return array{zone_id?:string,before?:callable|null,after?:callable|null,controls?:array<int,array<string,mixed>>}
	 */
	public function get_submit_controls(string $container_id): array;

	public function set_submit_controls(string $container_id, array $submit_controls): void;

	public function has_section(string $container_id, string $section_id): bool;

	/**
	 * @return array<string,mixed>
	 */
	public function get_section(string $container_id, string $section_id): array;

	public function set_section(string $container_id, string $section_id, array $section): void;

	public function has_fields(string $container_id, string $section_id): bool;

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_fields(string $container_id, string $section_id): array;

	/**
	 * @param array<int,array<string,mixed>> $fields
	 */
	public function set_fields(string $container_id, string $section_id, array $fields): void;

	public function has_group(string $container_id, string $section_id, string $group_id): bool;

	/**
	 * @return array<string,mixed>
	 */
	public function get_group(string $container_id, string $section_id, string $group_id): array;

	public function set_group(string $container_id, string $section_id, string $group_id, array $group): void;
}

class FormsStateStore implements FormsStateStoreInterface {
	protected array $containers;
	/** @var array<string, array<string, array{title:string, description_cb:string|callable|null, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $sections;
	/** @var array<string, array<string, array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int, before:?callable, after:?callable}>>> */
	protected array $fields;
	/** @var array<string, array<string, array{group_id:string, fields:array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	protected array $groups;
	/** @var array<string, array{zone_id:string, before:?callable, after:?callable, controls: array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int}>}> */
	protected array $submit_controls;

	public function __construct(
		array &$containers,
		array &$sections,
		array &$fields,
		array &$groups,
		array &$submit_controls
	) {
		$this->containers      = & $containers;
		$this->sections        = & $sections;
		$this->fields          = & $fields;
		$this->groups          = & $groups;
		$this->submit_controls = & $submit_controls;
	}

	public function get_registered_field_metadata(): array {
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

	public function lookup_component_alias(string $field_id): ?string {
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

	public function has_submit_controls(string $container_id): bool {
		return isset($this->submit_controls[$container_id]);
	}

	public function get_submit_controls(string $container_id): array {
		return $this->submit_controls[$container_id] ?? array();
	}

	public function set_submit_controls(string $container_id, array $submit_controls): void {
		$this->submit_controls[$container_id] = $submit_controls;
	}

	public function has_section(string $container_id, string $section_id): bool {
		return isset($this->sections[$container_id]) && isset($this->sections[$container_id][$section_id]);
	}

	public function get_section(string $container_id, string $section_id): array {
		$container = $this->sections[$container_id] ?? array();
		return is_array($container) ? ($container[$section_id] ?? array()) : array();
	}

	public function set_section(string $container_id, string $section_id, array $section): void {
		if (!isset($this->sections[$container_id]) || !is_array($this->sections[$container_id])) {
			$this->sections[$container_id] = array();
		}

		$this->sections[$container_id][$section_id] = $section;
	}

	public function has_fields(string $container_id, string $section_id): bool {
		return isset($this->fields[$container_id]) && isset($this->fields[$container_id][$section_id]);
	}

	public function get_fields(string $container_id, string $section_id): array {
		$container = $this->fields[$container_id] ?? array();
		if (!is_array($container)) {
			return array();
		}

		$section_fields = $container[$section_id] ?? array();
		return is_array($section_fields) ? $section_fields : array();
	}

	public function set_fields(string $container_id, string $section_id, array $fields): void {
		if (!isset($this->fields[$container_id]) || !is_array($this->fields[$container_id])) {
			$this->fields[$container_id] = array();
		}
		if (!isset($this->fields[$container_id][$section_id]) || !is_array($this->fields[$container_id][$section_id])) {
			$this->fields[$container_id][$section_id] = array();
		}

		$this->fields[$container_id][$section_id] = $fields;
	}

	public function has_group(string $container_id, string $section_id, string $group_id): bool {
		return isset($this->groups[$container_id])
			&& isset($this->groups[$container_id][$section_id])
			&& isset($this->groups[$container_id][$section_id][$group_id]);
	}

	public function get_group(string $container_id, string $section_id, string $group_id): array {
		$container = $this->groups[$container_id] ?? array();
		if (!is_array($container)) {
			return array();
		}

		$section = $container[$section_id] ?? array();
		if (!is_array($section)) {
			return array();
		}

		$group = $section[$group_id] ?? array();
		return is_array($group) ? $group : array();
	}

	public function set_group(string $container_id, string $section_id, string $group_id, array $group): void {
		if (!isset($this->groups[$container_id]) || !is_array($this->groups[$container_id])) {
			$this->groups[$container_id] = array();
		}
		if (!isset($this->groups[$container_id][$section_id]) || !is_array($this->groups[$container_id][$section_id])) {
			$this->groups[$container_id][$section_id] = array();
		}

		$this->groups[$container_id][$section_id][$group_id] = $group;
	}
}
