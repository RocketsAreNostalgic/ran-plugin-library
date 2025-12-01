<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

// Support both old-style ($context['sections']) and new-style ($context['inner_html']) usage
if (isset($context['inner_html']) && $context['inner_html'] !== '') {
	// New-style: pre-rendered content passed directly
	$before = (string) ($context['before'] ?? '');
	$after  = (string) ($context['after'] ?? '');
	$markup = $before . '<section class="test-section">' . $context['inner_html'] . '</section>' . $after;
} else {
	// Old-style: iterate over sections and render fields
	$sections      = $context['sections']       ?? array();
	$fieldRenderer = $context['field_renderer'] ?? null;
	$renderField   = is_callable($fieldRenderer) ? $fieldRenderer : static fn (): string => '';

	$markup = '';

	foreach ($sections as $section) {
		$before         = (string) ($section['before'] ?? '');
		$after          = (string) ($section['after'] ?? '');
		$sectionContent = '';

		foreach ($section['items'] ?? array() as $item) {
			if (($item['type'] ?? '') === 'field' && isset($item['field'])) {
				$fieldBefore = (string) ($item['before'] ?? '');
				$fieldAfter  = (string) ($item['after'] ?? '');
				$sectionContent .= $fieldBefore . $renderField($item['field']) . $fieldAfter;
				continue;
			}

			if (($item['type'] ?? '') === 'group') {
				$groupBefore  = (string) ($item['before'] ?? '');
				$groupAfter   = (string) ($item['after'] ?? '');
				$groupContent = '';
				foreach ($item['items'] ?? $item['fields'] ?? array() as $groupFieldItem) {
					$fieldData   = $groupFieldItem['field'] ?? $groupFieldItem;
					$fieldBefore = (string) ($groupFieldItem['before'] ?? '');
					$fieldAfter  = (string) ($groupFieldItem['after'] ?? '');
					$groupContent .= $fieldBefore . $renderField($fieldData) . $fieldAfter;
				}
				$sectionContent .= $groupBefore . $groupContent . $groupAfter;
			}
		}

		$markup .= '<section class="test-section">' . $before . $sectionContent . $after . '</section>';
	}
}

return new ComponentRenderResult(
	markup: $markup,
	component_type: ComponentType::LayoutWrapper
);
