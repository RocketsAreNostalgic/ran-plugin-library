<?php
/**
 * Interface for reusable field definitions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

interface ComponentBuilderDefinitionInterface {
	/**
	 * Get the field identifier.
	 */
	public function get_id(): string;

	/**
	 * Get the display label for this field.
	 */
	public function get_label(): string;

	/**
	 * Optional ordering hint.
	 */
	public function get_order(): ?int;

	/**
	 * Produce the normalized field array consumed by Settings builders.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array;
}
