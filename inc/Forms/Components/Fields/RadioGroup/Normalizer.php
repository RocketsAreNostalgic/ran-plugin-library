<?php
/**
 * Radio group component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		$name    = $this->_sanitize_string($context['name'] ?? '', 'name');
		$default = $this->_sanitize_string($context['default'] ?? '', 'default');

		// Generate fieldset ID
		$fieldsetId = '';
		$idSource   = $context['attributes']['id'] ?? ($context['id'] ?? ($name !== '' ? $name : null));
		if ($idSource !== null) {
			$fieldsetId                  = $this->session->reserveId(is_string($idSource) ? $idSource : null, 'fieldset');
			$context['attributes']['id'] = $fieldsetId;
		}

		// Validate options array using base class method
		$options = $this->_validate_config_array($context['options'] ?? null, 'options') ?? array();

		// Render individual options
		$renderedOptions = array();
		foreach ($options as $option) {
			$optionContext            = $option;
			$optionContext['name']    = $optionContext['name']    ?? $name;
			$optionContext['checked'] = $optionContext['checked'] ?? ($default !== '' && isset($option['value']) && $this->_sanitize_string($option['value'], 'option value') === $default);
			$renderedOptions[]        = $this->_render_option($optionContext);
		}

		// Build template context
		$context['legend']       = $this->_sanitize_string($context['legend'] ?? '', 'legend');
		$context['attributes']   = $this->session->formatAttributes($context['attributes']);
		$context['options_html'] = $renderedOptions;

		return $context;
	}

	/**
	 * Render individual radio option.
	 */
	private function _render_option(array $option): string {
		$attributes = isset($option['attributes']) && is_array($option['attributes']) ? $option['attributes'] : array();
		$name       = $this->_sanitize_string($option['name'] ?? '', 'option name');
		$value      = $this->_sanitize_string($option['value'] ?? '', 'option value');

		// Set radio attributes
		$attributes['type']  = 'radio';
		$attributes['name']  = $name;
		$attributes['value'] = $value;

		// Generate option ID
		$id = $this->_sanitize_string($option['id'] ?? '', 'option id');
		if ($id === '' && $name !== '' && $value !== '') {
			$id = $this->session->generateId($name, $value);
		}
		$optionId         = $this->session->reserveId($attributes['id'] ?? $id, 'radio_option');
		$attributes['id'] = $optionId;

		// Handle states using base class boolean sanitization
		if ($this->_sanitize_boolean($option['checked'] ?? false, 'option checked')) {
			$attributes['checked'] = 'checked';
		}
		if ($this->_sanitize_boolean($option['disabled'] ?? false, 'option disabled')) {
			$attributes['disabled'] = 'disabled';
		}

		// Handle option description using base class string sanitization
		$description   = $this->_sanitize_string($option['description'] ?? '', 'option description');
		$descriptionId = '';
		if ($description !== '') {
			$descriptionId = $this->session->reserveId($optionId . '__desc', 'desc');
			$this->session->appendAriaDescribedBy($attributes, $descriptionId);
		}

		// Validate label attributes using base class array validation
		$labelAttributes = $this->_validate_config_array($option['label_attributes'] ?? null, 'label_attributes') ?? array();

		$payload = $this->views->render_payload('fields.radio-option', array(
			'input_attributes' => $this->session->formatAttributes($attributes),
			'label'            => $this->_sanitize_string($option['label'] ?? '', 'option label'),
			'description'      => $description,
			'description_id'   => $descriptionId,
			'label_attributes' => $this->session->formatAttributes($labelAttributes),
		));

		if (!is_array($payload) || !isset($payload['markup'])) {
			$error = 'fields.radio-option must return component payload array.';
			$this->logger->error('Template payload validation failed', array(
				'template'     => 'fields.radio-option',
				'payload_type' => gettype($payload),
				'has_markup'   => is_array($payload) ? isset($payload['markup']) : false
			));
			throw new \UnexpectedValueException($error);
		}

		return $payload['markup'];
	}
}
