<?php
/**
 * AdminSettingsPageBuilder: Fluent builder for Settings pages.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\SettingsInterface;
use Ran\PluginLib\Settings\SectionBuilderInterface;
use Ran\PluginLib\Settings\SectionBuilder;
use Ran\PluginLib\Settings\CollectionBuilderInterface;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder;
use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;

/**
 * AdminSettingsPageBuilder: Fluent builder for Admin Settings pages.
 */
final class AdminSettingsPageBuilder implements CollectionBuilderInterface {
	private AdminSettingsMenuGroupBuilder $group;
	private string $page_slug;
	/** @var array{page_title:string, menu_title:string, capability:string, template:?callable, order:int} */
	private array $meta;
	/** @var callable */
	private $commit;
	// Buffered structure for this page
	/** @var array<string, array{title:string, description_cb:?callable, order:int, index:int}> */
	private array $sections = array();
	/** @var array<string, array<int, array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>> */
	private array $fields = array();
	/** @var array<string, array<string, array{group_id:string, title:string, fields:array<int, array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
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
	/** @var array<string, string> Template overrides for this page */
	private array $template_overrides = array();

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsMenuGroupBuilder $group The menu group builder.
	 * @param string $page_slug The page slug.
	 * @param array $initial_meta The initial meta data.
	 * @param callable $commit The commit callback.
	 */
	public function __construct(AdminSettingsMenuGroupBuilder $group, string $page_slug, array $initial_meta, callable $commit) {
		$this->group     = $group;
		$this->page_slug = $page_slug;
		$this->meta      = $initial_meta;
		$this->commit    = $commit;
	}

	/**
	 * Destructor - commits any buffered data.
	 */
	public function __destruct() {
		$this->_commit();
	}

	/**
	 * Set the page heading displayed atop the admin screen.
	 *
	 * @param string $page_title The page heading text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function page_heading(string $page_title): self {
		$this->meta['page_title'] = $page_title;
		return $this;
	}

	/**
	 * Set the sidebar menu label.
	 *
	 * @param string $menu_title The menu label text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function menu_label(string $menu_title): self {
		$this->meta['menu_title'] = $menu_title;
		return $this;
	}

	/**
	 * Set the capability.
	 * @see https://developer.wordpress.org/reference/functions/current_user_can/
	 *
	 * @param string $capability The capability required to access the page. e.g. 'manage_options'
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function capability(string $capability): self {
		$this->meta['capability'] = $capability;
		return $this;
	}

	/**
	 * Set the template.
	 *
	 * @param callable|null $template The template callback.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function template(?callable $template): self {
		$this->meta['template'] = $template;
		return $this;
	}

	/**
	 * Set the order.
	 *
	 * @param int|null $order The order.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function order(?int $order): self {
		$this->meta['order'] = $order;
		return $this;
	}

	/**
	 * Set the page template for complete page layout control.
	 *
	 * @param string $template_key The template key to use for page layout.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function page_template(string $template_key): self {
		$this->template_overrides['page'] = $template_key;
		return $this;
	}

	/**
	 * Set the default field template for all fields in this page.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function default_field_template(string $template_key): self {
		$this->template_overrides['field-wrapper'] = $template_key;
		return $this;
	}

	/**
	 * Set the default section template for all sections in this page.
	 *
	 * @param string $template_key The template key to use for section containers.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function default_section_template(string $template_key): self {
		$this->template_overrides['section'] = $template_key;
		return $this;
	}

	/**
	 * Define a section within this page.
	 *
	 * @param string $section_id The section ID.
	 * @param string $title      The section title.
	 * @param callable|null $description_cb The section description callback.
	 * @param int|null $order The section order.
	 *
	 * @return SectionBuilderInterface The SectionBuilderInterface instance.
	 */
	public function section(string $section_id, string $title, ?callable $description_cb = null, ?int $order = null): SectionBuilderInterface {
		// Buffer the section meta
		$this->sections[$section_id] = array(
		    'title'          => $title,
		    'description_cb' => $description_cb,
		    'order'          => ($order !== null ? (int) $order : 0),
		    'index'          => $this->__section_index++,
		);
		// Callbacks to mutate this builder's buffers
		$onAddSection = function (string $page, string $sid, string $stitle, ?callable $sdesc, ?int $sorder): void {
			if ($page !== $this->page_slug) {
				return;
			}
			$this->sections[$sid] = array(
			    'title'          => $stitle,
			    'description_cb' => $sdesc,
			    'order'          => ($sorder !== null ? (int) $sorder : 0),
			    'index'          => $this->__section_index++,
			);
		};
		$onAddField = function (string $page, string $sid, string $fid, string $label, string $component, array $context, ?int $forder, ?string $field_template = null): void {
			if ($page !== $this->page_slug) {
				return;
			}
			$component = trim($component);
			if ($component === '') {
				throw new \InvalidArgumentException(sprintf('Field "%s" requires a component alias.', $fid));
			}
			if (!isset($this->fields[$sid])) {
				$this->fields[$sid] = array();
			}
			if (!is_array($context)) {
				throw new \InvalidArgumentException(sprintf('Field "%s" must provide an array component_context.', $fid));
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
			if ($page !== $this->page_slug) {
				return;
			}
			if (!isset($this->groups[$sid])) {
				$this->groups[$sid] = array();
			}
			$norm = array();
			foreach ($fields as $entry) {
				if (!is_array($entry)) {
					throw new \InvalidArgumentException(sprintf('Field definitions for group "%s" must be arrays.', $gid));
				}
				if (!isset($entry['id'], $entry['label'], $entry['component'])) {
					throw new \InvalidArgumentException(sprintf('Field definition for group "%s" is missing required metadata.', $gid));
				}
				$component = trim((string) $entry['component']);
				if ($component === '') {
					throw new \InvalidArgumentException(sprintf('Field definition "%s" in group "%s" requires a component alias.', $entry['id'], $gid));
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
			if ($page !== $this->page_slug) {
				return;
			}
			if (!isset($this->fields[$sid])) {
				$this->fields[$sid] = array();
			}
			$field = $definition->to_array();
			if (!isset($field['component'])) {
				throw new \InvalidArgumentException(sprintf('Builder definition "%s" must provide component metadata.', $definition->get_id()));
			}
			$component = trim((string) $field['component']);
			if ($component === '') {
				throw new \InvalidArgumentException(sprintf('Builder definition "%s" must provide a non-empty component alias.', $definition->get_id()));
			}
			if (!isset($field['component_context']) || !is_array($field['component_context'])) {
				$field['component_context'] = array();
			}
			$field['component']   = $component;
			$field['index']       = $this->__field_index++;
			$this->fields[$sid][] = $field;
		};

		$builder = new SectionBuilder(
			$this,
			$this->page_slug,
			$section_id,
			$onAddSection,
			$onAddField,
			$onAddGroup,
			$onAddDefinition,
			function (string $page, string $sid): void {
				if ($page !== $this->page_slug) {
					return;
				}
				unset($this->active_sections[$sid]);
			}
		);
		$this->active_sections[$section_id] = $builder;
		return $builder;
	}

	/**
	 * Commit buffered data and return to the menu group builder.
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder {
		$this->_commit();
		return $this->group;
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 *
	 * @return SettingsInterface The SettingsInterface instance.
	 */
	public function end(): SettingsInterface {
		return $this->end_page()->end_group();
	}

	/**
	 * Check if the page has been committed.
	 *
	 * @return bool True if the page has been committed, false otherwise.
	 */
	public function is_committed(): bool {
		return $this->committed;
	}

	/**
	 * Apply template overrides to the AdminSettings instance.
	 */
	private function _apply_template_overrides(): void {
		if (!empty($this->template_overrides)) {
			// Get AdminSettings instance through the group without committing
			$admin_settings = $this->get_admin_settings();
			if ($admin_settings instanceof AdminSettingsInterface) {
				$admin_settings->set_page_template_overrides($this->page_slug, $this->template_overrides);
			}
		}
	}

	/**
	 * Get the AdminSettings instance from the group builder.
	 *
	 * @return AdminSettingsInterface
	 */
	public function get_admin_settings(): AdminSettingsInterface {
		// Access the settings instance through reflection to avoid committing the group
		$reflection = new \ReflectionClass($this->group);
		$property   = $reflection->getProperty('settings');
		$property->setAccessible(true);
		return $property->getValue($this->group);
	}

	/**
	 * Commit the page to the menu group.
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
		($this->commit)(
			$this->page_slug,
			$this->meta,
			$this->sections,
			$this->fields,
			$this->groups
		);
		$this->active_sections = array();
		$this->committed       = true;
	}
}
