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
		$url    = isset($context['url']) ? (string) $context['url'] : '';
		$target = isset($context['target']) ? (string) $context['target'] : '';
		$rel    = isset($context['rel']) ? (string) $context['rel'] : '';

		// Build CSS classes
		$classes = array('ran-forms__inline-link');
		if (isset($context['attributes']['class'])) {
			$classes[] = (string) $context['attributes']['class'];
		}
		$context['attributes']['class'] = trim(implode(' ', array_filter(array_map('trim', $classes))));

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
		$context['link_attributes'] = $this->session->formatAttributes($context['attributes']);
		$context['label']           = isset($context['label']) ? (string) $context['label'] : '';
		$context['url']             = $url;
		$context['target']          = $target;
		$context['rel']             = $rel;
		$context['icon_html']       = isset($context['icon_html']) ? (string) $context['icon_html'] : '';

		return $context;
	}
}
