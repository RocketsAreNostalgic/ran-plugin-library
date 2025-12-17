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

use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\FormsServiceSession;
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
	 * Constructor.
	 *
	 * @param RegisterOptions $options The RegisterOptions instance (required).
	 * @param ConfigInterface|null $config Optional Config for namespace resolution and component registration.
	 */
	public function __construct(RegisterOptions $options, ?ConfigInterface $config = null) {
		$context = $options->get_storage_context();
		$scope   = $context->scope instanceof OptionScope ? $context->scope : null;
		$logger  = $options->get_logger();

		// ComponentManifest always created internally
		$componentDir = new ComponentLoader(dirname(__DIR__) . '/Forms/Components', $logger);
		$registry     = new ComponentManifest($componentDir, $logger);

		try {
			$settings = $scope === OptionScope::User
				? new UserSettings($options, $registry, $config, $logger)
				: new AdminSettings($options, $registry, $config, $logger); // Site, Network, Blog
		} catch (\Exception $e) {
			throw new \LogicException('Invalid options object, failed to create settings instance.', 0, $e);
		}
		$this->inner = $settings;
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
	 * Bootstrap the settings by registering all WordPress hooks.
	 *
	 * This finalizes the builder configuration and registers the necessary
	 * WordPress hooks for rendering and saving:
	 * - For AdminSettings: registers admin menu pages, settings sections, and save handlers
	 * - For UserSettings: registers profile render and save hooks
	 *
	 * Must be called after all builder methods (settings_page, collection, section,
	 * field, etc.) have been invoked. If using `safe_boot()`, this is called
	 * automatically after the callback completes successfully.
	 *
	 * By default, uses lazy loading - hooks are only registered if the current
	 * request context requires this settings type. Set $eager to true to force
	 * immediate hook registration.
	 *
	 * @param bool $eager If true, skip lazy loading checks and always register hooks.
	 * @return void
	 */
	public function boot(bool $eager = false): void {
		$this->inner->boot($eager);
	}

	/**
	 * Execute a builder callback with error protection.
	 *
	 * Wraps the callback in a try-catch to prevent builder errors from crashing the site.
	 * On error, logs the exception and displays an admin notice in dev mode.
	 * Automatically calls boot() after the callback completes successfully.
	 *
	 * By default, uses lazy loading - hooks are only registered if the current
	 * request context requires this settings type. Set $eager to true to force
	 * immediate hook registration.
	 *
	 * @param callable $callback The builder callback, receives $this as argument.
	 * @param bool $eager If true, skip lazy loading checks and always register hooks.
	 * @return void
	 */
	public function safe_boot(callable $callback, bool $eager = false): void {
		$this->inner->safe_boot($callback, $eager);
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
	 * Resolve the correctly scoped RegisterOptions instance for current context.
	 *
	 * Returns the underlying RegisterOptions instance, optionally cloned with
	 * context-specific overrides (e.g., user_id for per-user storage). This is
	 * primarily used internally by save handlers and validation pipelines to
	 * access storage and schema information.
	 *
	 * Most developers should use the fluent builder API (settings_page, collection,
	 * section, field, etc.) rather than calling this directly.
	 *
	 * @internal Advanced API for custom save handlers and storage access.
	 *
	 * @param ?array $context Optional context overrides (e.g., ['user_id' => 123]).
	 *
	 * @return RegisterOptions The scoped RegisterOptions instance.
	 */
	public function resolve_options(?array $context = null): RegisterOptions {
		return $this->inner->resolve_options($context);
	}

	/**
	 * Override specific form-wide template defaults for this settings instance.
	 *
	 * Allows developers to customize which templates are used for form elements
	 * without replacing all defaults. Overrides are applied to the form session
	 * and affect all subsequent rendering.
	 *
	 * Example:
	 * ```php
	 * $settings->override_form_defaults([
	 *     'section' => 'custom.section-template',
	 *     'field'   => 'custom.field-wrapper',
	 * ]);
	 * ```
	 *
	 * @param array<string, string|callable> $overrides Template type => template key mappings.
	 * @return void
	 */
	public function override_form_defaults(array $overrides): void {
		$this->inner->override_form_defaults($overrides);
	}

	/**
	 * Get the FormsServiceSession instance for direct template and component access.
	 *
	 * Provides access to the underlying form session for advanced use cases such as:
	 * - Registering custom templates programmatically
	 * - Accessing the component manifest
	 * - Inspecting current template resolution state
	 *
	 * Most developers won't need this - use `override_form_defaults()` for template
	 * customization or the fluent builder API for form construction.
	 *
	 * @return FormsServiceSession|null The form session, or null if not yet initialized.
	 */
	public function get_form_session(): ?FormsServiceSession {
		return $this->inner->get_form_session();
	}

	/**
	 * Expose the underlying concrete settings instance when direct access is required.
	 *
	 * @return FormsInterface
	 */
	public function inner(): FormsInterface {
		if ($this->inner instanceof FormsInterface || $this->inner instanceof FormsInterface) {
			return $this->inner;
		}

		throw new \LogicException('Settings inner implementation must be FormsInterface.');
	}

	/**
	 * Render a settings page, collection, or section by its ID/slug.
	 *
	 * This is the internal rendering entry point called by WordPress hooks
	 * (e.g., admin page callbacks, profile hooks) to output the form HTML.
	 * It resolves the appropriate template, populates field values from storage,
	 * and renders the complete form structure.
	 *
	 * Developers should not call this directly - it's invoked automatically
	 * when WordPress renders the registered admin pages or profile sections.
	 *
	 * @internal Called by WordPress hooks during page/profile rendering.
	 *
	 * @param string     $id_slug The identifier for the page, collection, or section to render.
	 * @param array|null $context Optional rendering context (e.g., ['user' => WP_User]).
	 *
	 * @return void
	 */
	public function __render(string $id_slug, ?array $context = null): void {
		$this->inner->__render($id_slug, $context);
	}

	/**
	 * Magic method to proxy fluent builder calls to the inner settings instance.
	 *
	 * This enables the Settings facade to forward all builder method calls
	 * (e.g., settings_page(), collection(), section(), field()) to the
	 * underlying AdminSettings or UserSettings instance without explicitly
	 * defining each method on the facade.
	 *
	 * @param string $name      The method name being called.
	 * @param array  $arguments The arguments passed to the method.
	 *
	 * @return mixed The result from the inner settings instance method.
	 *
	 * @throws \BadMethodCallException If the method does not exist on the inner instance.
	 */
	public function __call(string $name, array $arguments): mixed {
		if (!\method_exists($this->inner, $name)) {
			throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', $this->inner::class, $name));
		}

		return $this->inner->{$name}(...$arguments);
	}
}
