<?php
/**
 * Button component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\Button;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	// Component type and template name now derived from component alias passed to render()

	protected function _normalize_component_specific(array $context): array {
		// Normalize button type using base class string sanitization
		$type                          = $this->_sanitize_string($context['type'] ?? 'button', 'type');
		$context['attributes']['type'] = $type !== '' ? $type : 'button';

		// Normalize variant using base class choice validation
		$variant = $this->_validate_choice(
			$context['variant'] ?? 'primary',
			array('primary', 'secondary'),
			'variant',
			'primary'
		);

		// Build CSS classes
		$baseClasses = array('ran-forms__button', $variant === 'secondary' ? 'ran-forms__button--secondary' : 'ran-forms__button--primary');
		if (isset($context['attributes']['class'])) {
			$baseClasses[] = $this->_sanitize_string($context['attributes']['class'], 'class');
		}
		$classList                      = implode(' ', array_filter(array_map('trim', $baseClasses)));
		$context['attributes']['class'] = $this->_sanitize_string($classList, 'class');

		// Build button attributes string for template
		$context['button_attributes'] = $this->session->formatAttributes($context['attributes']);
		$context['type']              = $type;
		$context['variant']           = $variant;
		$context['label']             = $this->_sanitize_string($context['label'] ?? '', 'label');
		$context['icon_html']         = $this->_sanitize_string($context['icon_html'] ?? '', 'icon_html');

		return $context;
	}
}
