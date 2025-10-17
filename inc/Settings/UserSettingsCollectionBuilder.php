<?php
/**
 * UserSettingsCollectionBuilder: Fluent builder for user profile pages.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\CollectionBuilderInterface;
use Ran\PluginLib\Settings\UserSettingsSectionBuilder;
use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;

/**
 * UserSettingsCollectionBuilder: Fluent builder for user settings collections.
 */
class UserSettingsCollectionBuilder implements CollectionBuilderInterface {
	private SettingsInterface $settings;
	private string $page_id;
	/** @var callable */
	private $commit;
	/** @var callable */
	private $setPriority;

	// Buffered structure for this page
	/** @var array<string, array{title:string, description_cb:?callable, order:int, index:int}> */
	private array $sections = array();
	/** @var array<string, array<int, array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>> */
	private array $fields = array();
	/** @var array<string, array<string, array{group_id:string, title:string, fields:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	private array $groups = array();
	/** @var int */
	private int $__section_index = 0;
	/** @var int */
	private int $__field_index = 0;
	/** @var int */
	private int $__group_index = 0;
	/** @var array<string, SectionBuilder> */
	private array $active_sections = array();
	private bool $committed        = false;

	/** @var array<string, string> Template overrides for this collection */
	private array $template_overrides = array();

	/**
	 * Constructor.
	 *
	 * @param SettingsInterface $settings The settings instance.
	 * @param string $page_id The page ID.
	 * @param ?callable $template The template callback.
	 * @param callable $commit The commit callback.
	 * @param callable $setPriority The setPriority callback.
	 */
	public function __construct(SettingsInterface $settings, string $page_id, ?callable $template, callable $commit, callable $setPriority) {
		$this->settings    = $settings;
		$this->page_id     = $page_id;
		$this->commit      = $commit;
		$this->setPriority = $setPriority;
		// Template is handled by the commit closure captured at Settings::page
		if ($template) {
			// no-op here; commit will store it on the Settings instance
		}
	}

	/**
	 * Destructor - commits any buffered data.
	 */
	public function __destruct() {
		$this->_commit();
	}

	/**
	 * A chainable mehtod to configure the render hook priority
	 * of this block within the users profile page.
	 * Higher numbers mean that the block will be rendered higher in the list of blocks on the profile page.
	 * Defaults to 10, which will render the block at the end of the list.
	 *
	 * @param int $priority The priority.
	 *
	 * @return self The UserPageBuilder instance.
	 */
	public function priority(int $priority = 10): self {
		$priority = $priority < 0 ? 0 : $priority;
		($this->setPriority)($this->page_id, $priority);
		return $this;
	}

	/**
	 * Define a new section within this custom profile collection.
	 *
	 * @param string        $section_id      The section ID.
	 * @param string        $title           The section title.
	 * @param callable|null $description_cb  The section description callback.
	 * @param int|null      $order           Optional ordering value.
	 *
	 * @return SectionBuilderInterface The SectionBuilder instance.
	 */
	public function section(string $section_id, string $title, ?callable $description_cb = null, ?int $order = null): SectionBuilderInterface {
		// Buffer the section meta immediately for optimistic chaining
		$this->sections[$section_id] = array(
		    'title'          => $title,
		    'description_cb' => $description_cb,
		    'order'          => ($order !== null ? (int) $order : 0),
		    'index'          => $this->__section_index++,
		);

		$onAddSection = function (string $page, string $sid, string $stitle, ?callable $sdesc, ?int $sorder): void {
			if ($page !== $this->page_id) {
				return;
			}
			$this->sections[$sid] = array(
			    'title'          => $stitle,
			    'description_cb' => $sdesc,
			    'order'          => ($sorder !== null ? (int) $sorder : 0),
			    'index'          => $this->__section_index++,
			);
		};

		$onAddField = function (string $page, string $sid, string $fid, string $label, string $component, array $context, ?int $forder): void {
			if ($page !== $this->page_id) {
				return;
			}
			$component = trim($component);
			if ($component === '') {
				throw new \InvalidArgumentException(sprintf('User field "%s" requires a component alias.', $fid));
			}
			if (!isset($this->fields[$sid])) {
				$this->fields[$sid] = array();
			}
			if (!is_array($context)) {
				throw new \InvalidArgumentException(sprintf('User field "%s" must provide an array component_context.', $fid));
			}
			$this->fields[$sid][] = array(
			    'id'                => $fid,
			    'label'             => $label,
			    'component'         => $component,
			    'component_context' => $context,
			    'order'             => ($forder !== null ? (int) $forder : 0),
			    'index'             => $this->__field_index++,
			);
		};

		$onAddGroup = function (string $page, string $sid, string $gid, string $gtitle, array $fields, ?callable $before, ?callable $after, ?int $gorder): void {
			if ($page !== $this->page_id) {
				return;
			}
			if (!isset($this->groups[$sid])) {
				$this->groups[$sid] = array();
			}
			$norm = array();
			foreach ($fields as $entry) {
				if (!is_array($entry)) {
					throw new \InvalidArgumentException(sprintf('User field group "%s" requires array definitions.', $gid));
				}
				if (!isset($entry['id'], $entry['label'], $entry['component'])) {
					throw new \InvalidArgumentException(sprintf('User field group "%s" definition is missing required metadata.', $gid));
				}
				$component = trim((string) $entry['component']);
				if ($component === '') {
					throw new \InvalidArgumentException(sprintf('User field "%s" in group "%s" requires a component alias.', $entry['id'], $gid));
				}
				$context = isset($entry['component_context']) && is_array($entry['component_context']) ? $entry['component_context'] : array();
				$norm[]  = array(
				    'id'                => (string) $entry['id'],
				    'label'             => (string) $entry['label'],
				    'component'         => $component,
				    'component_context' => $context,
				    'order'             => isset($entry['order']) ? (int) $entry['order'] : 0,
				    'index'             => $this->__field_index++,
				);
			}
			$this->groups[$sid][$gid] = array(
			    'group_id' => $gid,
			    'title'    => $gtitle,
			    'fields'   => $norm,
			    'before'   => $before,
			    'after'    => $after,
			    'order'    => ($gorder !== null ? (int) $gorder : 0),
			    'index'    => $this->__group_index++,
			);
		};

		$onAddDefinition = function (string $page, string $sid, BuilderDefinitionInterface $definition): void {
			if ($page !== $this->page_id) {
				return;
			}
			if (!isset($this->fields[$sid])) {
				$this->fields[$sid] = array();
			}
			$field = $definition->to_array();
			if (!isset($field['component'])) {
				throw new \InvalidArgumentException(sprintf('User builder definition "%s" must provide component metadata.', $definition->get_id()));
			}
			$component = trim((string) $field['component']);
			if ($component === '') {
				throw new \InvalidArgumentException(sprintf('User builder definition "%s" must provide a non-empty component alias.', $definition->get_id()));
			}
			if (!isset($field['component_context']) || !is_array($field['component_context'])) {
				$field['component_context'] = array();
			}
			$field['component']   = $component;
			$field['index']       = $this->__field_index++;
			$this->fields[$sid][] = $field;
		};

		$builder = new UserSettingsSectionBuilder(
			$this,
			$this->page_id,
			$section_id,
			$onAddSection,
			$onAddField,
			$onAddGroup,
			$onAddDefinition,
			function (string $page, string $sid): void {
				if ($page !== $this->page_id) {
					return;
				}
				unset($this->active_sections[$sid]);
			}
		);
		$this->active_sections[$section_id] = $builder;
		return $builder;
	}

	/**
	 * Set the collection template for collection-level wrapper overrides.
	 *
	 * @param string $template_key The template key to use for collection wrapper.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function collection_template(string $template_key): self {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}
		$this->template_overrides['collection-wrapper'] = $template_key;
		return $this;
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 *
	 * @return SettingsInterface The Settings instance.
	 */
	public function end(): SettingsInterface {
		$this->_commit();
		return $this->settings;
	}

	/**
	 * Check if the collection has been committed.
	 *
	 * @return bool True if the collection has been committed, false otherwise.
	 */
	public function is_committed(): bool {
		return $this->committed;
	}

	/**
	 * Apply template overrides to the UserSettings instance.
	 */
	private function _apply_template_overrides(): void {
		if (!empty($this->template_overrides)) {
			// Get UserSettings instance and apply collection template overrides
			if ($this->settings instanceof \Ran\PluginLib\Settings\UserSettings) {
				$this->settings->set_collection_template_overrides($this->page_id, $this->template_overrides);
			}
		}
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

		foreach ($this->active_sections as $builder) {
			$builder->end_section();
		}
		($this->commit)($this->page_id, $this->sections, $this->fields, $this->groups);
		$this->active_sections = array();
		$this->committed       = true;
	}
}
