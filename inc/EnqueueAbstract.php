<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

/**
 * This class is meant to be extended and be instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
abstract class EnqueueAbstract implements EnqueueInterface {

	public array $styles = array();
	public array $scripts = array();
	public array $media = array();

	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	 *
	 * @return null
	 */
	abstract public function load():void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @param  array $styles
	 *
	 * @return self
	 */
	public function add_styles( array $styles ):self {
		$this->styles = $styles;

		return $this;
	}

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @param  array $scripts
	 *
	 * @return self
	 */
	public function add_scripts( array $scripts ):self {
		$this->scripts = $scripts;

		return $this;
	}

	/**
	 * Chain-able call to add media to be loaded.
	 *
	 * @param  array $media
	 *
	 * @return self
	 */
	public function add_media( array $media ):self {
		$this->media = $media;

		return $this;
	}

	/**
	 * Enqueue an array of scripts.
	 *
	 * @param  array $scripts
	 *
	 * @return self
	 */
	public function enqueue_scripts( array $scripts ):self {

		foreach ( $scripts as $script ) {
			wp_enqueue_script( ...$script );
		}
		return $this;
	}

	/**
	 * Enqueue an array of styles.
	 *
	 * @param  array $styles
	 *
	 * @return self
	 */
	public function enqueue_styles( array $styles ):self {

		foreach ( $styles as $style ) {
			wp_enqueue_style( ...$style );
		}
		return $this;
	}

	/**
	 * Enqueue an array of media.
	 *
	 * @param  array $media
	 *
	 * @return self
	 */
	public function enqueue_media( array $media ):self {

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
	public function enqueue():void {
		$this->enqueue_scripts( $this->scripts );
		$this->enqueue_styles( $this->styles );
		$this->enqueue_media( $this->media );
	}
}
