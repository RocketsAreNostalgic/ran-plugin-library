<?php
/**
 * Base normalizer for text-based input components.
 * Provides common functionality for text inputs, textareas, and other text-based elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Normalize;

abstract class NormalizerTextBase extends NormalizerInputBase {
	/**
	 * Normalize text-specific context that extends input normalization.
	 *
	 * @param array $context Component context
	 * @return array Normalized context
	 */
	protected function _normalize_text_context(array $context): array {
		// Start with input normalization
		$context = $this->_normalize_input_context($context);

		// Handle text-specific attributes
		$context = $this->_normalize_text_attributes($context);

		return $context;
	}

	/**
	 * Normalize text-specific attributes (autocomplete, autocapitalize, spellcheck, etc.).
	 *
	 * @param array $context Component context
	 * @return array Normalized context
	 */
	protected function _normalize_text_attributes(array $context): array {
		$attributes = &$context['attributes'];

		// Handle autocomplete
		if (isset($context['autocomplete'])) {
			$attributes['autocomplete'] = $this->_sanitize_string($context['autocomplete'], 'autocomplete');
		}

		// Handle autocapitalize
		if (isset($context['autocapitalize'])) {
			$autocapitalize = $this->_validate_choice(
				$context['autocapitalize'],
				array('none', 'sentences', 'words', 'characters'),
				'autocapitalize',
				'sentences'
			);
			$attributes['autocapitalize'] = $autocapitalize;
		}

		// Handle spellcheck
		if (isset($context['spellcheck'])) {
			$spellcheck               = $this->_sanitize_boolean($context['spellcheck'], 'spellcheck');
			$attributes['spellcheck'] = $spellcheck ? 'true' : 'false';
		}

		// Handle minlength
		if (isset($context['minlength'])) {
			$minlength = (int) $context['minlength'];
			if ($minlength > 0) {
				$attributes['minlength'] = (string) $minlength;
			}
		}

		// Handle maxlength
		if (isset($context['maxlength'])) {
			$maxlength = (int) $context['maxlength'];
			if ($maxlength > 0) {
				$attributes['maxlength'] = (string) $maxlength;
			}
		}

		// Handle pattern
		if (isset($context['pattern'])) {
			$attributes['pattern'] = $this->_sanitize_string($context['pattern'], 'pattern');
		}

		return $context;
	}

	/**
	 * Complete text normalization pipeline.
	 * This method combines all text-specific normalizations.
	 *
	 * @param array $context Component context
	 * @param string $fallbackType Fallback type for ID generation
	 * @return array Fully normalized context
	 */
	protected function _complete_text_normalization(array $context, string $fallbackType = 'text'): array {
		$context = $this->_normalize_text_context($context);
		$context = $this->_normalize_input_id($context, $fallbackType);
		$context = $this->_build_input_attributes($context);
		return $context;
	}
}
