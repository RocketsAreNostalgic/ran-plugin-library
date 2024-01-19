<?php

declare(strict_types=1);
/**
 * Abstract Enqueue implementation.
 * TODO: add optional support to add cache busting query param to end of urls.
 * - It will be difficult with our current approach to do this on a per item basis.
 * - It would be easy however to add a flag to enqueue_*($scripts, $cashbust=true)
 *
 * @package  RanPluginLib
 */

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * This class is meant to be extended and be instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
abstract class EnqueueAbstract implements EnqueueInterface {

	/**
	 * Array of styles to enqueue.
	 *
	 * @var array
	 */
	public array $styles = array();

	/**
	 *  Array of urls to enqueue.
	 *
	 * @var array
	 */
	public array $scripts = array();

	/**
	 *  Array of media elements to enqueue.
	 *
	 * @var array
	 */
	public array $media = array();

	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * It runs: add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	 *
	 * @return null
	 */
	abstract public function load(): void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @param  array $styles - The array of styles to enqueue.
	 *
	 * @return self
	 */
	public function add_styles( array $styles ): self {
		$this->styles = $styles;

		return $this;
	}

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @param  array $scripts - The array of scripts to enqueue.
	 *
	 * @return self
	 */
	public function add_scripts( array $scripts ): self {
		$this->scripts = $scripts;

		return $this;
	}

	/**
	 * Chain-able call to add media to be loaded.
	 *
	 * @param  array $media - The array of media to enqueue.
	 *
	 * @return self
	 */
	public function add_media( array $media ): self {
		$this->media = $media;

		return $this;
	}

	/**
	 * Enqueue an array of scripts.
	 *
	 * @param  array $scripts - The array of scripts to enqueue.
	 *
	 * @return self
	 */
	public function enqueue_scripts( array $scripts ): self {
		foreach ( $scripts as $script ) {
			wp_enqueue_script( ...$script );
		}
		return $this;
	}

	/**
	 * Enqueue an array of styles.
	 *
	 * @param  array $styles - The array of styles to enqueue.
	 *
	 * @return self
	 */
	public function enqueue_styles( array $styles ): self {

		foreach ( $styles as $style ) {
			wp_enqueue_style( ...$style );
		}
		return $this;
	}

	/**
	 * Enqueue an array of media.
	 *
	 * @param  array $media - The array of media to enqueue.
	 *
	 * @return self
	 */
	public function enqueue_media( array $media ): self {

		foreach ( $media as $args ) {
			wp_enqueue_media( $args );
		}

		return $this;
	}

	/**
	 * Enqueue all registered scripts, styles and media
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$this->enqueue_scripts( $this->scripts );
		$this->enqueue_styles( $this->styles );
		$this->enqueue_media( $this->media );
	}
}
