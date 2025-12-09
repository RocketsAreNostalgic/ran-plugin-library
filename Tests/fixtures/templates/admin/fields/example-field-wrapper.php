<?php
/**
 * Example AdminSettings Field Wrapper Template
 *
 * Returns a ComponentRenderResult with the field wrapper markup.
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$field_id   = isset($context['field_id']) ? (string) $context['field_id'] : '';
$label      = isset($context['label']) ? (string) $context['label'] : '';
$inner_html = isset($context['inner_html']) ? (string) $context['inner_html'] : '';

// Check nested context for before/after (they may be nested under 'context')
$nested_context = isset($context['context']) && is_array($context['context']) ? $context['context'] : array();
$before         = (string) ($nested_context['before'] ?? $context['before'] ?? '');
$after          = (string) ($nested_context['after'] ?? $context['after'] ?? '');

$markup = $before . '<div class="field-wrapper" data-field-id="' . htmlspecialchars($field_id, ENT_QUOTES) . '">'
	. '<label>' . htmlspecialchars($label, ENT_QUOTES) . '</label>'
	. $inner_html
	. '</div>' . $after;

return new ComponentRenderResult(
	markup: $markup,
	component_type: 'layout_wrapper'
);
