<?php
/**
 * Text component normalizer.
 *
 * Normalizes single-line text input fields with text-specific attribute support
 * including autocomplete, autocapitalize, spellcheck, minlength, maxlength, and pattern.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Text;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerInputBase;

final class Normalizer extends NormalizerInputBase {
	/**
	 * Normalize text-specific context.
	 *
	 * Forces input_type to 'text' and handles text-specific attributes.
	 *
	 * @param array<string,mixed> $context Component context.
	 * @return array<string,mixed> Normalized context.
	 */
	protected function _normalize_component_specific(array $context): array {
		// Force input type to text
		$context['input_type']         = 'text';
		$context['attributes']['type'] = 'text';

		// Normalize text-specific attributes
		$context = $this->_normalize_text_attributes($context);

		// Use the complete input normalization pipeline
		return $this->_complete_input_normalization($context, 'text');
	}

	/**
	 * Normalize text-specific attributes.
	 *
	 * @param array<string,mixed> $context Component context.
	 * @return array<string,mixed> Normalized context.
	 */
	private function _normalize_text_attributes(array $context): array {
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

		// Handle size (visible width in characters)
		if (isset($context['size'])) {
			$size = (int) $context['size'];
			if ($size > 0) {
				$attributes['size'] = (string) $size;
			}
		}

		return $context;
	}
}
