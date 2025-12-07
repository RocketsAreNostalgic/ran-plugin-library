<?php
/**
 * Inline link component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\InlineLink;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Normalize link properties
		$url    = $this->_sanitize_string($context['url'] ?? '', 'url');
		$target = $this->_sanitize_string($context['target'] ?? '', 'target');
		$rel    = $this->_sanitize_string($context['rel'] ?? '', 'rel');

		// Build CSS classes
		$classes = array('kplr-link');
		if (isset($context['attributes']['class'])) {
			$classes[] = $this->_sanitize_string($context['attributes']['class'], 'class');
		}
		$classList                      = implode(' ', array_filter(array_map('trim', $classes)));
		$context['attributes']['class'] = $this->_sanitize_string($classList, 'class');

		// Set link attributes
		$context['attributes']['href'] = $url;
		if ($target !== '') {
			$context['attributes']['target'] = $target;
			if ($rel === '' && strtolower($target) === '_blank') {
				$rel = 'noopener noreferrer';
			}
		}
		if ($rel !== '') {
			$context['attributes']['rel'] = $rel;
		}

		// Build template context
		$context['link_attributes'] = $this->session->format_attributes($context['attributes']);
		$context['label']           = $this->_sanitize_string($context['label'] ?? '', 'label');
		$context['url']             = $url;
		$context['target']          = $target;
		$context['rel']             = $rel;
		$context['icon_html']       = $this->_sanitize_string($context['icon_html'] ?? '', 'icon_html');

		return $context;
	}
}
