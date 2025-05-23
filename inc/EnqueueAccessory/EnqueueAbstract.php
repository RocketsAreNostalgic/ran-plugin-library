<?php
/**
 * Abstract Enqueue implementation.
 *
 * This class provides functionality for enqueueing scripts, styles, and media in WordPress.
 * TODO: add optional support to add cache busting query param to end of urls.
 * - It will be difficult with our current approach to do this on a per item basis.
 * - It would be easy however to add a flag to enqueue_*($scripts, $cashbust=true)
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

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
	 * @var array<int, array<int, mixed>>
	 */
	public array $styles = array();

	/**
	 * Array of urls to enqueue.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public array $scripts = array();

	/**
	 * Array of media elements to enqueue.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $media = array();

	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * It runs: add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	 */
	abstract public function load(): void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @param  array<int, array<int, mixed>> $styles - The array of styles to enqueue.
	 */
	public function add_styles( array $styles ): self {
		$this->styles = $styles;

		return $this;
	}

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @param  array<int, array<int, mixed>> $scripts - The array of scripts to enqueue.
	 */
	public function add_scripts( array $scripts ): self {
		$this->scripts = $scripts;

		return $this;
	}

	/**
	 * Chain-able call to add media to be loaded.
	 *
	 * @param  array<int, array<string, mixed>> $media - The array of media to enqueue.
	 */
	public function add_media( array $media ): self {
		$this->media = $media;

		return $this;
	}

	/**
	 * Enqueue an array of scripts.
	 *
	 * @param  array<int, array<int, mixed>> $scripts - The array of scripts to enqueue.
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
	 * @param  array<int, array<int, mixed>> $styles - The array of styles to enqueue.
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
	 * @param  array<int, array<string, mixed>> $media - The array of media to enqueue.
	 */
	public function enqueue_media( array $media ): self {

		foreach ( $media as $args ) {
			wp_enqueue_media( $args );
		}

		return $this;
	}

	/**
	 * Enqueue all registered scripts, styles and media
	 */
	public function enqueue(): void {
		$this->enqueue_scripts( $this->scripts );
		$this->enqueue_styles( $this->styles );
		$this->enqueue_media( $this->media );
	}
}
