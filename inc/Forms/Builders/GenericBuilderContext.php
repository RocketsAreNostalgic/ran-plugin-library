<?php
/**
 * GenericBuilderContext: Context implementation for standalone/generic builders.
 *
 * Provides the BuilderContextInterface implementation for core builders
 * (GroupBuilder, FieldsetBuilder, SectionBuilder) that operate outside
 * of Settings-specific contexts.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;

/**
 * Context for standalone/generic builders.
 *
 * Used by core builders in Forms/Builders/ that don't have a Settings-specific context.
 * Also serves as the foundation for future FrontendForms builders.
 */
class GenericBuilderContext implements BuilderContextInterface {
	/**
	 * The forms instance.
	 */
	private FormsInterface $forms;

	/**
	 * The container ID (page slug, collection ID, etc.).
	 */
	private string $container_id;

	/**
	 * The update callback for immediate data flow.
	 *
	 * @var callable
	 */
	private $updateFn;

	/**
	 * Cached component builder factories.
	 *
	 * @var array<string, callable>|null
	 */
	private ?array $componentBuilderFactories = null;

	/**
	 * @param FormsInterface $forms The forms instance.
	 * @param string $container_id The container ID.
	 * @param callable $updateFn The update callback.
	 */
	public function __construct(
		FormsInterface $forms,
		string $container_id,
		callable $updateFn
	) {
		$this->forms        = $forms;
		$this->container_id = $container_id;
		$this->updateFn     = $updateFn;
	}

	/**
	 * Get the forms instance.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface {
		return $this->forms;
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null The factory or null if not found.
	 */
	public function get_component_builder_factory(string $component): ?callable {
		$component = trim($component);
		if ($component === '') {
			return null;
		}

		if ($this->componentBuilderFactories === null) {
			$session = $this->forms->get_form_session();
			if ($session === null) {
				$this->componentBuilderFactories = array();
			} else {
				$this->componentBuilderFactories = $session->manifest()->builder_factories();
			}
		}

		return $this->componentBuilderFactories[$component] ?? null;
	}

	/**
	 * Get the update callback.
	 *
	 * @return callable
	 */
	public function get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Get the container ID.
	 *
	 * @return string
	 */
	public function get_container_id(): string {
		return $this->container_id;
	}
}
