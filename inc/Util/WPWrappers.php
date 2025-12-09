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
 * Note: The _do_* methods inherited from WPWrappersTrait are internal implementation
 * details and should not be called directly by consuming code.
 *
 * @internal The _do_* methods are for internal use only.
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
