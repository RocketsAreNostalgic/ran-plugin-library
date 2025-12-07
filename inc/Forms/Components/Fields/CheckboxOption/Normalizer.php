<?php
/**
 * Checkbox option component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Validate required name attribute using base class string sanitization
		$name = $this->_sanitize_string($context['name'] ?? '', 'name');
		if ($name === '') {
			$error = 'Checkbox option requires a name attribute.';
			$this->logger->error('Missing required name attribute', array(
				'component_type' => $this->componentType,
				'context_keys'   => array_keys($context)
			));
			throw new \InvalidArgumentException($error);
		}

		// Set checkbox attributes
		$context['attributes']['type']  = 'checkbox';
		$context['attributes']['name']  = $name;
		$context['attributes']['value'] = $this->_sanitize_string($context['value'] ?? 'on', 'value');

		// Generate and set ID
		$fieldId                     = $this->_extract_field_id($context);
		$idSource                    = $context['attributes']['id'] ?? ($context['id'] ?? ($fieldId !== '' ? $fieldId : null));
		$optionId                    = $this->session->reserve_id(is_string($idSource) ? $idSource : null, 'checkbox_option');
		$context['attributes']['id'] = $optionId;

		// Handle checked state using base class boolean sanitization
		$checked = $this->_sanitize_boolean($context['checked'] ?? false, 'checked') || ($this->_sanitize_boolean($context['default_checked'] ?? false, 'default_checked') && !isset($context['checked']));
		if ($checked) {
			$context['attributes']['checked'] = 'checked';
		}

		// Build template context
		$context['input_attributes'] = $this->session->format_attributes($context['attributes']);
		$context['label']            = $this->_sanitize_string($context['label'] ?? '', 'label');

		return $context;
	}
}
