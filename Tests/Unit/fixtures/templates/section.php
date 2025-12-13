<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Support both old-style ($context['inner_html']) and new-style ($context['sections']) usage
if (isset($context['sections']) && is_array($context['sections'])) {
	// New-style: iterate over sections and render fields
	$renderer = $context['field_renderer'] ?? null;
	$markup   = '<div class="sections-wrapper">';
	foreach ($context['sections'] as $section) {
		$markup .= '<section class="theme-section">';
		$markup .= '<h2>' . htmlspecialchars($section['title'] ?? '', ENT_QUOTES) . '</h2>';
		foreach ($section['items'] ?? array() as $item) {
			if (($item['type'] ?? '') === 'field' && is_callable($renderer)) {
				$markup .= $renderer($item['field']);
			}
		}
		$markup .= '</section>';
	}
	$markup .= '</div>';
} else {
	// Old-style: simple content wrapper
	$markup = '<section class="theme-section">' . ($context['inner_html'] ?? '') . '</section>';
}

return new ComponentRenderResult(
	markup: $markup
);
