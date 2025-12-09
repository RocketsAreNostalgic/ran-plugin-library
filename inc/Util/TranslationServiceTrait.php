<?php
/**
 * TranslationServiceTrait.php
 * A trait containing TranslationService-aware variants of WordPress translation functions.
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Util;

/**
 * Trait TranslationServiceTrait
 *
 * Provides TranslationService-aware variants of WordPress translation functions,
 * allowing for domain-level and message-level translation overrides.
 *
 * @package Ran\PluginLib\Util
 */
trait TranslationServiceTrait {
	use WPWrappersTrait;
	/**
	 * TranslationService-aware variant of WordPress __() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Translated text
	 */
	public function __do__service(string $text, TranslationService $translator): string {
		return $this->_do___($text, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress _x() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate
	 * @param string $context Context for translation
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Translated text
	 */
	public function __do_x_service(string $text, string $context, TranslationService $translator): string {
		return $this->_do_x($text, $context, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress esc_html__() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Escaped and translated text
	 */
	public function __do_esc_html__service(string $text, TranslationService $translator): string {
		return $this->_do_esc_html__($text, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress esc_html_x() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param string $context Context for translation
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Escaped and translated text
	 */
	public function __do_esc_html_x_service(string $text, string $context, TranslationService $translator): string {
		return $this->_do_esc_html_x($text, $context, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress esc_attr__() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape for attributes
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Escaped and translated text
	 */
	public function __do_esc_attr__service(string $text, TranslationService $translator): string {
		return $this->_do_esc_attr__($text, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress esc_attr_x() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape for attributes
	 * @param string $context Context for translation
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Escaped and translated text
	 */
	public function __do_esc_attr_x_service(string $text, string $context, TranslationService $translator): string {
		return $this->_do_esc_attr_x($text, $context, $translator->get_effective_domain());
	}

	/**
	 * Echo TranslationService-aware variant of WordPress _e() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and echo
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return void
	 */
	public function __do_e_service(string $text, TranslationService $translator): void {
		$this->_do_e($text, $translator->get_effective_domain());
	}

	/**
	 * Echo TranslationService-aware variant of WordPress esc_html_e() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate, escape, and echo
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return void
	 */
	public function __do_esc_html_e_service(string $text, TranslationService $translator): void {
		$this->_do_esc_html_e($text, $translator->get_effective_domain());
	}

	/**
	 * Echo TranslationService-aware variant of WordPress esc_attr_e() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate, escape for attributes, and echo
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return void
	 */
	public function __do_esc_attr_e_service(string $text, TranslationService $translator): void {
		$this->_do_esc_attr_e($text, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress _n() function
	 * Availability-guarded: Yes
	 *
	 * @param string $single Singular text
	 * @param string $plural Plural text
	 * @param int $number Number to determine singular/plural
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Translated text
	 */
	public function __do_translate_plural_service(string $single, string $plural, int $number, TranslationService $translator): string {
		return $this->_do_n($single, $plural, $number, $translator->get_effective_domain());
	}

	/**
	 * TranslationService-aware variant of WordPress _nx() function
	 * Availability-guarded: Yes
	 *
	 * @param string $single Singular text
	 * @param string $plural Plural text
	 * @param int $number Number to determine singular/plural
	 * @param string $context Context for translation
	 * @param \Ran\PluginLib\Util\TranslationService $translator Translation service instance
	 * @return string Translated text
	 */
	public function __do_nx_service(string $single, string $plural, int $number, string $context, TranslationService $translator): string {
		return $this->_do_nx($single, $plural, $number, $context, $translator->get_effective_domain());
	}
}
