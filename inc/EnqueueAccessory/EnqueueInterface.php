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
	 * A class registration function to add admin_stage_scripts/wp_enqueue_scripts hooks to WP.
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
	 * Process the scripts queue.
	 * Any asset with a hook will be moved to the deferred queue and processed automatically.
	 * Any asset without a hook will be processed once the enqueue_immediate_scripts() method is called.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function stage_scripts(): self;

	/**
	 * Process the styles queue.
	 * Any asset with a hook will be moved to the deferred queue and processed automatically.
	 * Any asset without a hook will be processed once the enqueue_immediate_styles() method is called.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function stage_styles(): self;

	/**
	 * Process and enqueue any non-deferred scripts.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_immediate_scripts(): self;

	/**
	 * Process and enqueue any non-deferred styles.
	 *
	 * @since 1.0.0
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_immediate_styles(): self;


	/**
	 * Enqueue an array of media.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $media Array of media to be enqueued.
	 * @return self Returns the current instance for method chaining.
	 */
	public function enqueue_media( array $media ): self;

	/**
	 * Chain-able call to add multiple inline scripts.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_scripts_to_add A single inline script definition array or an array of them.
	 * @return self
	 */
	public function add_inline_scripts( array $inline_scripts_to_add ): self;

	/**
	 * Chain-able call to add multiple inline styles.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_styles_to_add A single inline style definition array or an array of them.
	 * @return self
	 */
	public function add_inline_styles( array $inline_styles_to_add ): self;

	/**
	 * Enqueue all registered assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue(): void;
}
