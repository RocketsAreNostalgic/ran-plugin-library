<?php
/**
 * SettingsBuilderContext: Shared builder context for AdminSettings and UserSettings.
 *
 * Encapsulates context-specific dependencies for fluent builders, providing
 * access to the FormsInterface, component factories, and update callbacks.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;

/**
 * Builder context for Settings (AdminSettings and UserSettings).
 *
 * Provides access to context-specific dependencies via dependency injection,
 * allowing shared base classes to access services without tight coupling.
 */
final class SettingsBuilderContext implements BuilderContextInterface {
	private FormsInterface $forms;
	private string $container_id;

	/** @var callable */
	private $updateFn;

	/** @var array<string, callable>|null Lazily loaded component builder factories */
	private ?array $componentBuilderFactories = null;

	/**
	 * @param FormsInterface $forms The forms instance.
	 * @param string $container_id The container ID (page slug or collection ID).
	 * @param callable $updateFn The update callback for immediate data flow.
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
	 * Get the FormsInterface instance for this context.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface {
		return $this->forms;
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * Lazily loads the factory map from the form session's manifest.
	 *
	 * @param string $component The component alias (e.g., 'fields.input').
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
	 * Get the update callback for immediate data flow.
	 *
	 * @return callable
	 */
	public function get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Get the container ID for this context.
	 *
	 * @return string
	 */
	public function get_container_id(): string {
		return $this->container_id;
	}
}
