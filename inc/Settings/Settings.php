<?php
/**
 * Settings: Scope-aware facade that wraps the appropriate settings implementation.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Scope-aware facade that wraps the appropriate settings implementation.
 */
final class Settings implements FormsInterface {
	/**
	 * @var FormsInterface
	 */
	private FormsInterface $inner;

	/**
	 * @var ConfigInterface|null Optional Config for namespace resolution and component registration.
	 */
	private ?ConfigInterface $config = null;

	/**
	 * Constructor.
	 *
	 * @param RegisterOptions $options The RegisterOptions instance (required).
	 * @param ConfigInterface|null $config Optional Config for namespace resolution and component registration.
	 */
	public function __construct(RegisterOptions $options, ?ConfigInterface $config = null) {
		$this->config = $config;

		$context = $options->get_storage_context();
		$scope   = $context->scope instanceof OptionScope ? $context->scope : null;

		// Logger from Config, or fallback to RegisterOptions logger
		$registryLogger = $config?->get_logger() ?? $options->get_logger();

		// ComponentManifest always created internally
		$componentDir = new ComponentLoader(dirname(__DIR__) . '/Forms/Components', $registryLogger);
		$registry     = new ComponentManifest($componentDir, $registryLogger);

		try {
			$settings = $scope === OptionScope::User
				? new UserSettings($options, $registry, $config, $registryLogger)
				: new AdminSettings($options, $registry, $config, $registryLogger); // Site, Network, Blog
		} catch (\Exception $e) {
			throw new \LogicException('Invalid options object, failed to create settings instance.', 0, $e);
		}
		$this->inner = $settings;
	}

	public function resolve_options(?array $context = null): RegisterOptions {
		return $this->inner->resolve_options($context);
	}

	public function boot(): void {
		$this->inner->boot();
	}

	public function render(string $id_slug, ?array $context = null): void {
		$this->inner->render($id_slug, $context);
	}

	public function override_form_defaults(array $overrides): void {
		$this->inner->override_form_defaults($overrides);
	}

	public function get_form_session(): ?FormsServiceSession {
		return $this->inner->get_form_session();
	}

	/**
	 * Register a single external component.
	 *
	 * Requires Config to be provided at construction time for namespace resolution.
	 *
	 * @param string $name Component name (e.g., 'color-picker')
	 * @param array{path: string, prefix?: string} $options Component options
	 * @return static For fluent chaining
	 */
	public function register_component(string $name, array $options): static {
		$this->inner->register_component($name, $options);
		return $this;
	}

	/**
	 * Register multiple external components from a directory.
	 *
	 * Requires Config to be provided at construction time for namespace resolution.
	 *
	 * @param array{path: string, prefix?: string} $options Batch options
	 * @return static For fluent chaining
	 */
	public function register_components(array $options): static {
		$this->inner->register_components($options);
		return $this;
	}

	/**
	 * Expose the underlying concrete settings instance when direct access is required.
	 */
	public function inner(): FormsInterface {
		if ($this->inner instanceof FormsInterface || $this->inner instanceof FormsInterface) {
			return $this->inner;
		}

		throw new \LogicException('Settings inner implementation must be FormsInterface.');
	}

	public function __call(string $name, array $arguments): mixed {
		if (!\method_exists($this->inner, $name)) {
			throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', $this->inner::class, $name));
		}

		return $this->inner->{$name}(...$arguments);
	}
}
