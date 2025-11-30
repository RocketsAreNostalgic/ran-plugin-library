<?php
/**
 * Base normalizer for input-like form components.
 * Provides common functionality for text inputs, textareas, and other input elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Normalize;

abstract class NormalizerInputBase extends NormalizerBase {
	/**
	 * Normalize input-specific context that extends base normalization.
	 * Child classes should call this and extend the returned array.
	 *
	 * @param array $context Component context
	 * @return array Normalized context
	 */
	protected function _normalize_input_context(array $context): array {
		// NOTE: Do NOT call _normalize_context() here - it's already called by
		// NormalizerBase::render() before _normalize_component_specific().
		// Calling it here would create infinite recursion.

		// Handle common input attributes
		$context = $this->_normalize_name($context);
		$context = $this->_normalize_value($context);
		$context = $this->_normalize_placeholder($context);

		// Handle input states
		$context = $this->_normalize_input_states($context);

		return $context;
	}

	/**
	 * Normalize common input states (disabled, readonly, required, autofocus).
	 *
	 * @param array $context Component context
	 * @return array Normalized context
	 */
	protected function _normalize_input_states(array $context): array {
		$attributes = &$context['attributes'];

		// Handle disabled state
		if (!empty($context['disabled'])) {
			$attributes['disabled'] = 'disabled';
		}

		// Handle readonly state
		if (!empty($context['readonly'])) {
			$attributes['readonly'] = 'readonly';
		}

		// Handle required state (already handled in base class, but ensure consistency)
		if (!empty($context['required'])) {
			$attributes['required']      = 'required';
			$attributes['aria-required'] = 'true';
		}

		// Handle autofocus state
		if (!empty($context['autofocus'])) {
			$attributes['autofocus'] = 'autofocus';
		}

		return $context;
	}

	/**
	 * Generate and set ID for input elements.
	 *
	 * @param array $context Component context
	 * @param string $fallbackType Fallback type for ID generation
	 * @return array Context with ID set
	 */
	protected function _normalize_input_id(array $context, string $fallbackType = 'input'): array {
		$inputId                     = $this->_generate_and_reserve_id($context, $fallbackType);
		$context['attributes']['id'] = $inputId;
		return $context;
	}

	/**
	 * Build input attributes string for template.
	 *
	 * @param array $context Component context
	 * @return array Context with input_attributes set
	 */
	protected function _build_input_attributes(array $context): array {
		$context['input_attributes'] = $this->session->formatAttributes($context['attributes']);
		return $context;
	}

	/**
	 * Complete input normalization pipeline.
	 * This method combines all input-specific normalizations.
	 *
	 * @param array $context Component context
	 * @param string $fallbackType Fallback type for ID generation
	 * @return array Fully normalized context
	 */
	protected function _complete_input_normalization(array $context, string $fallbackType = 'input'): array {
		$context = $this->_normalize_input_context($context);
		$context = $this->_normalize_input_id($context, $fallbackType);
		$context = $this->_build_input_attributes($context);
		return $context;
	}
}
