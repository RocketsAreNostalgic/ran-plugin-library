<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

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
			$sectionContent .= $renderField($item['field']);
			continue;
		}

		if (($item['type'] ?? '') === 'group') {
			foreach ($item['fields'] ?? array() as $groupField) {
				$sectionContent .= $renderField($groupField);
			}
		}
	}

	$markup .= '<section class="test-section">' . $before . $sectionContent . $after . '</section>';
}

return new ComponentRenderResult(
	markup: $markup,
	component_type: ComponentType::LayoutWrapper
);