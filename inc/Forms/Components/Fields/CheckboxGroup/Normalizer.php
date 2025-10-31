<?php
/**
 * Checkbox group component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Generate fieldset ID if needed
		$fieldsetId = '';
		$idSource   = $context['attributes']['id'] ?? ($context['id'] ?? null);
		if ($idSource !== null) {
			$fieldsetId                  = $this->session->reserveId(is_string($idSource) ? $idSource : null, 'checkbox_group');
			$context['attributes']['id'] = $fieldsetId;
		}

		// Validate options array using base class method
		$options = $this->_validate_config_array($context['options'] ?? null, 'options') ?? array();

		// Render individual options
		$renderedOptions = array();
		foreach ($options as $index => $option) {
			$renderedOptions[] = $this->_render_option($option, $fieldsetId, $index);
		}

		// Build template context
		$context['attributes']   = $this->session->formatAttributes($context['attributes']);
		$context['legend']       = $this->_sanitize_string($context['legend'] ?? '', 'legend');
		$context['options_html'] = $renderedOptions;

		return $context;
	}

	/**
	 * Render individual checkbox option.
	 */
	private function _render_option(array $option, string $fieldsetId, int $index): string {
		$attributes = isset($option['attributes']) && is_array($option['attributes']) ? $option['attributes'] : array();

		// Set checkbox type and states using base class boolean sanitization
		$attributes['type'] = 'checkbox';
		if ($this->_sanitize_boolean($option['checked'] ?? false, 'option checked')) {
			$attributes['checked'] = 'checked';
		}
		if ($this->_sanitize_boolean($option['disabled'] ?? false, 'option disabled')) {
			$attributes['disabled'] = 'disabled';
		}

		// Generate option ID
		$optionIdBase     = $attributes['id'] ?? ($fieldsetId !== '' ? $fieldsetId . '__option-' . ($index + 1) : null);
		$optionId         = $this->session->reserveId(is_string($optionIdBase) ? $optionIdBase : null, 'checkbox_option');
		$attributes['id'] = $optionId;

		// Handle option description using base class string sanitization
		$optionDesc   = $this->_sanitize_string($option['description'] ?? '', 'option description');
		$optionDescId = '';
		if ($optionDesc !== '') {
			$optionDescId = $this->session->reserveId($optionId . '__desc', 'desc');
			$this->session->appendAriaDescribedBy($attributes, $optionDescId);
		}

		$payload = $this->views->render_payload('fields.checkbox-option', array(
			'input_attributes' => $this->session->formatAttributes($attributes),
			'label'            => $this->_sanitize_string($option['label'] ?? '', 'option label'),
			'description'      => $optionDesc,
			'description_id'   => $optionDescId,
		));

		if (!is_array($payload) || !isset($payload['markup'])) {
			$error = 'fields.checkbox-option must return component payload array.';
			$this->logger->error('Template payload validation failed', array(
				'template'     => 'fields.checkbox-option',
				'payload_type' => gettype($payload),
				'has_markup'   => is_array($payload) ? isset($payload['markup']) : false
			));
			throw new \UnexpectedValueException($error);
		}

		return $payload['markup'];
	}
}
