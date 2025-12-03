<?php
/**
 * FieldProxyInterface: Public API for field configuration proxies.
 *
 * This interface defines the fluent methods available when configuring a field.
 * ComponentBuilderProxy implements this interface.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Interface for field configuration proxies.
 *
 * @template TParent of SectionBuilder|SectionFieldContainerBuilder|GroupBuilder|FieldsetBuilder
 */
interface FieldProxyInterface {
	/**
	 * Set the field ID.
	 *
	 * @param string $id The field ID.
	 *
	 * @return static
	 */
	public function id(string $id): static;

	/**
	 * Set the visual style for this field.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return static
	 */
	public function style(string|callable $style): static;

	/**
	 * Set the field wrapper template.
	 *
	 * @param string $template The template key.
	 *
	 * @return static
	 */
	public function template(string $template): static;

	/**
	 * Set the field order.
	 *
	 * @param int $order The field order.
	 *
	 * @return static
	 */
	public function order(int $order): static;

	/**
	 * Set the before callback for this field.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return static
	 */
	public function before(?callable $before): static;

	/**
	 * Set the after callback for this field.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return static
	 */
	public function after(?callable $after): static;

	/**
	 * End field configuration and return to the parent builder.
	 *
	 * @return TParent
	 */
	public function end_field(): SectionBuilder|SectionFieldContainerBuilder|GroupBuilder|FieldsetBuilder;
}
