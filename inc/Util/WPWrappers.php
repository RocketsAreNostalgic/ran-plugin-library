<?php
/**
 * WPWrappers class - concrete implementation of WPWrappersTrait for use in non-class contexts.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Util;

/**
 * Concrete class that uses WPWrappersTrait and TranslationServiceTrait for accessing
 * WordPress functions and translation services in templates and other non-class contexts.
 *
 * @codeCoverageIgnore
 */
final class WPWrappers {
	use WPWrappersTrait;
	use TranslationServiceTrait;

	/**
	 * Get a singleton instance of WPWrappers.
	 *
	 * @return self
	 */
	public static function instance(): self {
		static $instance = null;

		if ($instance === null) {
			$instance = new self();
		}

		return $instance;
	}
}
