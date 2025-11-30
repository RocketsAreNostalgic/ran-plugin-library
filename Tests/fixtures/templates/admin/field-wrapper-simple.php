<?php

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

$componentHtml = isset($context['component_html']) ? (string) $context['component_html'] : '';

// Extract before/after from nested context or top-level
$nested_context = isset($context['context']) && is_array($context['context']) ? $context['context'] : array();
$before         = (string) ($context['before'] ?? $nested_context['before'] ?? '');
$after          = (string) ($context['after'] ?? $nested_context['after'] ?? '');

return new ComponentRenderResult(
	$before . '<div class="test-field-wrapper">' . $componentHtml . '</div>' . $after,
	component_type: ComponentType::LayoutWrapper
);
