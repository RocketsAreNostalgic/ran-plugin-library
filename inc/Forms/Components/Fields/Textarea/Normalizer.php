<?php
/**
 * Textarea component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Textarea;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerTextBase;

final class Normalizer extends NormalizerTextBase {
	protected function _normalize_component_specific(array $context): array {
		// Use the complete text normalization pipeline
		$context = $this->_complete_text_normalization($context, 'textarea');

		// Handle textarea-specific attributes
		if (isset($context['rows'])) {
			$rows = (int) $context['rows'];
			if ($rows > 0) {
				$context['attributes']['rows'] = (string) $rows;
			}
		}

		if (isset($context['cols'])) {
			$cols = (int) $context['cols'];
			if ($cols > 0) {
				$context['attributes']['cols'] = (string) $cols;
			}
		}

		// Handle value/default using base class string sanitization
		$value = '';
		if (isset($context['value'])) {
			$value = $this->_sanitize_string($context['value'], 'value');
		} elseif (isset($context['default'])) {
			$value = $this->_sanitize_string($context['default'], 'default');
		}

		// Build template context (override input_attributes with textarea_attributes)
		$context['textarea_attributes'] = $context['input_attributes'];
		unset($context['input_attributes']);
		$context['value'] = $value;

		return $context;
	}
}
