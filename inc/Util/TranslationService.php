<?php
/**
 * Translation service providing flexible message translation with override capabilities.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Util;

/**
 * Centralized translation service that supports both domain-level and message-level overrides.
 *
 * Provides a consistent translation API across all library components while allowing
 * consumers to customize messages at different levels of granularity.
 */
class TranslationService {
	use WPWrappersTrait;
	private string $defaultDomain;
	private string $hookPrefix;

	/**
	 * Create a new translation service.
	 *
	 * @param string $defaultDomain The default translation domain to use.
	 * @param string $hookPrefix The WordPress filter hook prefix for customization.
	 */
	public function __construct(string $defaultDomain = 'ran-plugin-lib', string $hookPrefix = 'ran/plugin_lib') {
		$this->defaultDomain = $defaultDomain;
		$this->hookPrefix    = $hookPrefix;
	}

	/**
	 * Translate a message with support for granular overrides.
	 *
	 * Translation priority:
	 * 1. Specific message override via {hookPrefix}/translation_overrides filter
	 * 2. Domain override via {hookPrefix}/translation_domain filter
	 * 3. Default domain
	 *
	 * @param string $message The message to translate.
	 * @param string $context Optional context for the translation.
	 *
	 * @return string The translated message.
	 */
	public function translate(string $message, string $context = ''): string {
		// 1. Check for specific message override
		$overrides = $this->_do_apply_filter("{$this->hookPrefix}/translation_overrides", array());
		if (isset($overrides[$message])) {
			return $overrides[$message];
		}

		// 2. Check for domain override
		$effectiveDomain = $this->_do_apply_filter("{$this->hookPrefix}/translation_domain", $this->defaultDomain);

		// 3. Translate with effective domain
		if ($context !== '') {
			return $this->_do_x($message, $context, $effectiveDomain);
		}

		return $this->_do___($message, $effectiveDomain);
	}

	/**
	 * Translate a plural message with support for granular overrides.
	 *
	 * Translation priority:
	 * 1. Specific message override via {hookPrefix}/translation_overrides filter
	 * 2. Domain override via {hookPrefix}/translation_domain filter
	 * 3. Default domain
	 *
	 * @param string $single The singular message to translate.
	 * @param string $plural The plural message to translate.
	 * @param int $number The number to determine singular/plural.
	 * @param string $context Optional context for the translation.
	 *
	 * @return string The translated message.
	 */
	public function translat_plural(string $single, string $plural, int $number, string $context = ''): string {
		// 1. Check for specific message override (use singular as key)
		$overrides = $this->_do_apply_filter("{$this->hookPrefix}/translation_overrides", array());
		if (isset($overrides[$single])) {
			// For overrides, we assume the override handles pluralization
			return $overrides[$single];
		}

		// 2. Check for domain override
		$effectiveDomain = $this->_do_apply_filter("{$this->hookPrefix}/translation_domain", $this->defaultDomain);

		// 3. Translate with effective domain
		if ($context !== '') {
			return $this->_do_nx($single, $plural, $number, $context, $effectiveDomain);
		}

		return $this->_do_n($single, $plural, $number, $effectiveDomain);
	}

	/**
	 * Get the current effective translation domain.
	 *
	 * @return string The domain that would be used for translation.
	 */
	public function get_effective_domain(): string {
		return $this->_do_apply_filter("{$this->hookPrefix}/translation_domain", $this->defaultDomain);
	}

	/**
	 * Get the hook prefix used by this service.
	 *
	 * @return string The hook prefix.
	 */
	public function get_hook_prefix(): string {
		return $this->hookPrefix;
	}

	/**
	 * Create a translation service for a specific domain.
	 *
	 * @param string $domain The domain path (e.g., 'forms/validator', 'settings', 'options/storage').
	 * @param string $textDomain The WordPress text domain to use for translations.
	 *
	 * @return self
	 * @throws \InvalidArgumentException If the domain is invalid.
	 */
	public static function for_domain(string $domain, string $textDomain = 'ran-plugin-lib'): self {
		$validatedDomain = self::validate_domain($domain);
		$hookPrefix      = 'ran/plugin_lib/' . $validatedDomain;

		return new self($textDomain, $hookPrefix);
	}

	/**
	 * Validate and sanitize a domain path.
	 *
	 * @param string $domain The domain to validate.
	 *
	 * @return string The validated domain.
	 * @throws \InvalidArgumentException If the domain is invalid.
	 */
	private static function validate_domain(string $domain): string {
		// Trim whitespace
		$domain = trim($domain);

		// Check for empty domain
		if ($domain === '') {
			throw new \InvalidArgumentException('Translation domain cannot be empty.');
		}

		// Remove leading/trailing slashes
		$domain = trim($domain, '/');

		// Check for invalid characters (allow letters, numbers, hyphens, underscores, and forward slashes)
		if (!preg_match('/^[a-z0-9_\/-]+$/i', $domain)) {
			throw new \InvalidArgumentException(
				'Translation domain can only contain letters, numbers, hyphens, underscores, and forward slashes.'
			);
		}

		// Convert to lowercase
		$domain = strtolower($domain);

		// Check for consecutive slashes
		if (strpos($domain, '//') !== false) {
			throw new \InvalidArgumentException('Translation domain cannot contain consecutive slashes.');
		}

		// Check for invalid segments (empty segments between slashes)
		$segments = explode('/', $domain);
		foreach ($segments as $segment) {
			if ($segment === '') {
				throw new \InvalidArgumentException('Translation domain cannot contain empty segments.');
			}
		}

		return $domain;
	}
}
