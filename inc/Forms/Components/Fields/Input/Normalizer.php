<?php
/**
 * Input component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Input;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerInputBase;

final class Normalizer extends NormalizerInputBase {
	/** @var array<int,string> */
	private array $validInputTypes = array(
		'checkbox', 'color', 'date', 'datetime-local', 'email', 'file', 'hidden',
		'image', 'number', 'password', 'radio', 'range', 'reset',
		'search', 'submit', 'tel', 'text', 'time', 'url',
	);

	protected function _normalize_component_specific(array $context): array {
		// Normalize input type using base class choice validation
		$inputType = $this->_validate_choice(
			$context['input_type'] ?? 'text',
			$this->validInputTypes,
			'input_type',
			'text'
		);
		$context['attributes']['type'] = $inputType;

		// Apply text-specific normalization only for text-based input types
		if ($this->_is_text_input_type($inputType)) {
			$context = $this->_normalize_text_attributes($context);
		}

		// Use the complete input normalization pipeline
		return $this->_complete_input_normalization($context, 'input');
	}

	/**
	 * Check if the input type is text-based and should have text attributes.
	 *
	 * @param string $inputType
	 * @return bool
	 */
	private function _is_text_input_type(string $inputType): bool {
		$textInputTypes = array(
			'text', 'email', 'url', 'tel', 'search', 'password'
		);
		return in_array($inputType, $textInputTypes, true);
	}

	/**
	 * Normalize text-specific attributes for text-based input types.
	 * This is a simplified version of the text normalization from NormalizerTextBase.
	 *
	 * @param array $context Component context
	 * @return array Normalized context
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

		return $context;
	}
}

