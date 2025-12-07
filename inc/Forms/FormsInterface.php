<?php
/**
 * FormsInterface: Interface for forms.
 *
 * @package Ran\PluginLib\Forms
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\FormsServiceSession;
interface FormsInterface {
	/**
	 * Render a profile collection.
	 *
	 * @internal WordPress callback for rendering forms.
	 *
	 * @param string $id_or_slug The collection id, defaults to 'profile'.
	 * @param array $context optional context.
	 *
	 * @return void
	 */
	public function _render(string $id_slug, ?array $context = null): void;

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current context.
	 * Callers can chain fluent API on the returned object.
	 *
	 * @param ?array $context optional context.
	 *
	 * @return RegisterOptions The RegisterOptions instance.
	 */
	public function resolve_options(?array $context = null): RegisterOptions;

	/**
	 * Bootstrap the settings.
	 *
	 * @return void
	 */
	public function boot(): void;

	/**
	 * Execute a builder callback with error protection.
	 *
	 * Wraps the callback in a try-catch to prevent builder errors from crashing the site.
	 * On error, logs the exception and displays an admin notice in dev mode.
	 * Automatically calls boot() after the callback completes successfully.
	 *
	 * @param callable $callback The builder callback, receives $this as argument.
	 * @return void
	 */
	public function safe_boot(callable $callback): void;

	/**
	 * Override specific form-wide defaults for AdminForms context.
	 * Allows developers to customize specific templates without replacing all defaults.
	 *
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function override_form_defaults(array $overrides): void;

	/**
	 * Get the FormsServiceSession instance for direct access to template resolution.
	 *
	 * @return FormsServiceSession|null The FormsServiceSession instance or null if not started
	 */
	public function get_form_session(): ?FormsServiceSession;

	/**
	 * Register a single external component.
	 *
	 * @param string $name Component name (e.g., 'color-picker')
	 * @param array{path: string, prefix?: string} $options Component options
	 * @return static For fluent chaining
	 */
	public function register_component(string $name, array $options): static;

	/**
	 * Register multiple external components from a directory.
	 *
	 * @param array{path: string, prefix?: string} $options Batch options
	 * @return static For fluent chaining
	 */
	public function register_components(array $options): static;
}
