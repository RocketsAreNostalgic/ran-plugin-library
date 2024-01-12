<?php

declare(strict_types=1);
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * An basic interface for enqueueing script and styles, which be instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
interface EnqueueInterface
{

	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * @return null
	 */
	public function load(): void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @param  array $styles
	 *
	 * @return self
	 */
	public function add_styles(array $styles): self;

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @param  array $scripts
	 *
	 * @return self
	 */
	public function add_scripts(array $scripts): self;

	/**
	 * Chain-able call to add media to be loaded.
	 *
	 * @param  array $media
	 *
	 * @return self
	 */
	public function add_media(array $media): self;

	/**
	 * Enqueue an array of scripts
	 *
	 * @param  array $scripts
	 *
	 * @return self
	 */
	public function enqueue_scripts(array $scripts): self;

	/**
	 * Enqueue an array of scripts
	 *
	 * @param  array $styles
	 *
	 * @return self;
	 */
	public function enqueue_styles(array $styles): self;

	/**
	 * Enqueue an array of media
	 *
	 * @param  array $media
	 *
	 * @return self
	 */
	public function enqueue_media(array $media): self;

	/**
	 * * Enqueue all registered assets.
	 *
	 * @return void
	 */
	public function enqueue(): void;
}
