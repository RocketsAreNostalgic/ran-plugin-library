<?php
/**
 * EnqueueInterface file.
 *
 * This file contains the interface for enqueueing scripts and styles in WordPress.
 *
 * @package  Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * An interface for enqueueing scripts and styles, which can be instantiated via the RegisterServices Class.
 *
 * @since 1.0.0
 */
interface EnqueueInterface {
	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * @since 1.0.0
	 */
	public function load(): void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $styles Array of styles to be registered.
	 * @return self Returns the current instance for method chaining.
	 */
	public function add_styles( array $styles ): self;

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $scripts Array of scripts to be registered.
	 * @return self Returns the current instance for method chaining.
	 */
	public function add_scripts( array $scripts ): self;

	/**
	 * Chain-able call to add media to be loaded.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $media Array of media to be registered.
	 * @return self Returns the current instance for method chaining.
	 */
	public function add_media( array $media ): self;

	/**
	 * Enqueue an array of scripts.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_scripts(): self;

	/**
	 * Enqueue all registered styles.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_styles(): self;

	/**
	 * Enqueue an array of media.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $media Array of media to be enqueued.
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_media( array $media ): self;

	/**
	 * Enqueue all registered assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue(): void;
}
